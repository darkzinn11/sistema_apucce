<?php

namespace App\Http\Controllers;

use App\Models\Piloto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PilotoController extends Controller
{
    // lista
    public function index()
    {
        return Piloto::orderBy('id', 'desc')->get();
    }

    // helper: encontra piloto por CPF tentando vários nomes de coluna
    protected function findByCpf($cpf)
    {
        $candidates = ['cpf_piloto', 'cpf', 'cpfPiloto', 'id_piloto'];
        foreach ($candidates as $col) {
            if (Schema::hasColumn('pilotos', $col)) {
                $q = Piloto::where($col, $cpf)->first();
                if ($q) return $q;
            }
        }
        return null;
    }

    // helper: get writable columns in pilotos table
    protected function tableColumns()
    {
        return Schema::hasTable('pilotos') ? Schema::getColumnListing('pilotos') : [];
    }

    // helper: safe map payload keys -> db columns (aliases)
    protected function mapPayloadToDb(array $payload)
    {
        $cols = $this->tableColumns();
        $out = [];

        // simple alias map: frontend keys -> possible db columns (ordered)
        $aliases = [
            'cpf_piloto' => ['cpf_piloto','cpf','id_piloto'],
            'nome_piloto' => ['nome_piloto','nome','name'],
            'email_piloto' => ['email_piloto','email'],
            'numero_telefone' => ['numero_telefone','telefone','phone'],
            'data_nascimento' => ['data_nascimento','nascimento','birthdate'],
            'estado_civil' => ['estado_civil','estadoCivil','civil_status'],
            'tipo_sanguineo' => ['tipo_sanguineo','tipoSanguineo','blood_type'],
            'nome_contato_seguranca' => ['nome_contato_seguranca','contatoEmergencia','emergency_contact_name'],
            'numero_contato_seguranca' => ['numero_contato_seguranca','foneEmergencia','emergency_contact_phone'],
            'nome_plano_saude' => ['nome_plano_saude','planoSaude','health_plan'],
            'foto_piloto' => ['foto_piloto','foto','avatar'],
            'foto_cnh' => ['foto_cnh','cnh','foto_cnh'],
            'foto_cnh_tipo' => ['foto_cnh_tipo','cnh_tipo'],
            'termo_adesao' => ['termo_adesao','termo','termo_adesao'],
            'termo_adesao_tipo' => ['termo_adesao_tipo','termo_tipo'],
            'id_piloto' => ['id_piloto'],
        ];

        foreach ($aliases as $payloadKey => $dbCandidates) {
            if (!isset($payload[$payloadKey])) continue;
            foreach ($dbCandidates as $c) {
                if (in_array($c, $cols)) {
                    $out[$c] = $payload[$payloadKey];
                    break;
                }
            }
        }

        // also copy any payload keys that already match a column name
        foreach ($payload as $k => $v) {
            if (in_array($k, $cols) && !isset($out[$k])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    // show
    public function show($cpf)
    {
        $piloto = $this->findByCpf($cpf);
        if (!$piloto) {
            return response()->json(['message' => 'Piloto não encontrado'], 404);
        }
        return response()->json($piloto, 200);
    }

    // store (cria Piloto e cria/associa Usuario do tipo USER automaticamente)
    public function store(Request $request)
    {
        Log::debug('Piloto.store payload:', $request->all());

        // validação mínima: apenas garantir nome+cpf presentes no payload do frontend
        $validator = Validator::make($request->all(), [
            'cpf_piloto' => 'required|string',
            'nome_piloto' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $payload = $request->all();

        // normaliza data_nascimento (aceita YYYY-MM-DD, ISO, DD/MM/YYYY)
        if (!empty($payload['data_nascimento'])) {
            try {
                $d = $payload['data_nascimento'];
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $d)) {
                    [$day,$month,$year] = explode('/',$d);
                    $payload['data_nascimento'] = "{$year}-{$month}-{$day}";
                } elseif (strpos($d,'T') !== false) {
                    $payload['data_nascimento'] = substr($d,0,10);
                }
            } catch (\Throwable $e) {
                Log::warning('data_nascimento parse failed: '.$e->getMessage());
                $payload['data_nascimento'] = null;
            }
        }

        // mapear somente colunas existentes
        $dbData = $this->mapPayloadToDb($payload);

        DB::beginTransaction();
        try {
            // verifica duplicidade conforme colunas existentes
            $exists = $this->findByCpf($payload['cpf_piloto']);
            if ($exists) {
                DB::rollBack();
                return response()->json(['message' => 'Piloto com esse CPF já existe'], 409);
            }

            // cria piloto
            $piloto = Piloto::create($dbData);

            // lógica de criação/ligação de usuário
            $tempPassword = null;
            $createdUser = null;
            $emailToUse = $payload['email_piloto'] ?? $payload['email'] ?? null;
            $nomeToUse = $payload['nome_piloto'] ?? $payload['nome'] ?? null;

            if ($emailToUse) {
                // se já existe usuário com esse email, só liga
                $usuario = Usuario::where('email', $emailToUse)->first();

                if (!$usuario) {
                    // gerar senha temporária (8 caracteres seguros)
                    $tempPassword = substr(bin2hex(random_bytes(4)),0,8);

                    $usuario = Usuario::create([
                        'nome' => $nomeToUse ?? $emailToUse,
                        'email' => $emailToUse,
                        'senha_hash' => Hash::make($tempPassword), // mantemos o hash no controller, como pediu
                        'tipo' => 'USER',
                        'is_active' => true,
                    ]);
                    $createdUser = $usuario;
                }

                // tenta associar FK usuario_id se existir a coluna
                if (Schema::hasColumn('pilotos', 'usuario_id')) {
                    $piloto->usuario_id = $usuario->id;
                    $piloto->save();
                } elseif (Schema::hasColumn('pilotos', 'email_usuario')) {
                    $piloto->email_usuario = $usuario->email;
                    $piloto->save();
                }
            }

            DB::commit();

            // montar resposta: incluir senha temporária somente se geramos um novo usuário
            $response = ['piloto' => $piloto];
            if ($createdUser && $tempPassword) {
                $response['temp_password'] = $tempPassword;
                $response['note'] = 'Senha temporária retornada apenas para o administrador. Em produção envie por e-mail seguro.';
            }

            return response()->json($response, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao criar Piloto: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao criar piloto', 'detail' => $e->getMessage()], 500);
        }
    }

    // update
    public function update(Request $request, $cpf)
    {
        Log::debug("Piloto.update payload for cpf {$cpf}:", $request->all());

        $piloto = $this->findByCpf($cpf);
        if (!$piloto) {
            return response()->json(['message' => 'Piloto não encontrado'], 404);
        }

        $payload = $request->all();

        if (!empty($payload['data_nascimento'])) {
            try {
                $d = $payload['data_nascimento'];
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $d)) {
                    [$day,$month,$year] = explode('/',$d);
                    $payload['data_nascimento'] = "{$year}-{$month}-{$day}";
                } elseif (strpos($d,'T') !== false) {
                    $payload['data_nascimento'] = substr($d,0,10);
                }
            } catch (\Throwable $e) {
                Log::warning('data_nascimento parse failed update: '.$e->getMessage());
                $payload['data_nascimento'] = null;
            }
        }

        $dbData = $this->mapPayloadToDb($payload);

        try {
            $piloto->update(array_filter($dbData, function($v){ return $v !== null; }));
            return response()->json($piloto, 200);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar Piloto: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao atualizar piloto', 'detail' => $e->getMessage()], 500);
        }
    }

    // delete
    public function destroy($cpf)
    {
        $piloto = $this->findByCpf($cpf);
        if (!$piloto) {
            return response()->json(['message' => 'Piloto não encontrado'], 404);
        }
        $piloto->delete();
        return response()->json(['message' => 'Piloto removido com sucesso'], 200);
    }

    /**
     * UPLOADS: endpoints específicos que o frontend usa (multipart/form-data)
     * POST /api/pilotos/{cpf}/cnh  -> campo file
     * POST /api/pilotos/{cpf}/termo -> campo file
     *
     * Os arquivos são salvos em storage/app/public/pilotos/{cpf}/
     * E atualizamos o registro do piloto (coluna foto_cnh ou termo_adesao se existirem).
     */

    public function uploadCnh(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message' => 'Nenhum arquivo enviado'], 400);

        $piloto = $this->findByCpf($cpf);
        if (!$piloto) return response()->json(['message' => 'Piloto não encontrado'], 404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);
        // tentar atualizar coluna correta
        if (Schema::hasColumn('pilotos','foto_cnh')) {
            $piloto->foto_cnh = $publicPath;
        } elseif (Schema::hasColumn('pilotos','foto_piloto')) {
            // fallback
            $piloto->foto_piloto = $publicPath;
        }
        if (Schema::hasColumn('pilotos','foto_cnh_tipo')) {
            $piloto->foto_cnh_tipo = $file->getClientMimeType();
        }
        $piloto->save();

        return response()->json(['message' => 'CNH enviada', 'path' => $publicPath], 200);
    }

    public function uploadTermo(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message' => 'Nenhum arquivo enviado'], 400);

        $piloto = $this->findByCpf($cpf);
        if (!$piloto) return response()->json(['message' => 'Piloto não encontrado'], 404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);

        if (Schema::hasColumn('pilotos','termo_adesao')) {
            $piloto->termo_adesao = $publicPath;
        } else {
            // fallback: tenta criar coluna? (não criar automaticamente aqui)
            Log::warning("Campo termo_adesao não existe na tabela pilotos");
        }
        if (Schema::hasColumn('pilotos','termo_adesao_tipo')) {
            $piloto->termo_adesao_tipo = $file->getClientMimeType();
        }
        $piloto->save();

        return response()->json(['message' => 'Termo enviado', 'path' => $publicPath], 200);
    }
}
