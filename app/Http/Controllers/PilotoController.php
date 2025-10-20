<?php

namespace App\Http\Controllers;

use App\Models\Piloto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PilotoController extends Controller
{
    /* ======================== utils ======================== */

    private function hasCol(string $col): bool
    {
        return Schema::hasColumn('pilotos', $col);
    }

    private function getFirst(Request $r, array $keys, $default = null)
    {
        foreach ($keys as $k) {
            $v = $r->input($k);
            if ($v !== null && $v !== '') return $v;
        }
        return $default;
    }

    private function normalizeDate(?string $value): ?string
    {
        if (!$value) return null;
        try {
            // 24/11/2004 -> 2004-11-24
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                [$d,$m,$y] = explode('/', $value);
                return "{$y}-{$m}-{$d}";
            }
            // 2004-11-24T00:00:00 -> 2004-11-24
            if (str_contains($value, 'T')) return substr($value, 0, 10);
            // 2004-11-24 -> 2004-11-24
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        } catch (\Throwable $e) {
            Log::warning('normalizeDate: '.$e->getMessage());
        }
        return null;
    }

    /** Se vier dataURL retorna só o base64; se vier base64 cru, devolve como está. */
    private function stripDataUrl(string $maybeDataUrl): string
    {
        if (str_starts_with($maybeDataUrl, 'data:')) {
            [$meta, $raw] = explode(',', $maybeDataUrl, 2);
            return $raw;
        }
        return $maybeDataUrl;
    }

    /** Decide como persistir mídia; aqui guardamos base64 direto no banco (mediumtext/longtext). */
    private function persistBase64(?string $value): ?string
    {
        if (!$value) return null;
        return $this->stripDataUrl($value);
    }

    private function serializePiloto(Piloto $p): array
    {
        $arr = $p->toArray();
        if (!empty($arr['data_nascimento'])) {
            $arr['data_nascimento'] = substr($arr['data_nascimento'], 0, 10).'T12:00:00';
        }
        return $arr;
    }

    /* ======================== endpoints ======================== */

    public function index()
    {
        return Piloto::orderByDesc('id')->get();
    }

    public function show($cpf)
    {
        $p = Piloto::where('cpf_piloto', $cpf)->first();
        if (!$p) return response()->json(['message' => 'Piloto não encontrado'], 404);
        return response()->json($this->serializePiloto($p), 200);
    }

    public function showByEmail($email)
    {
        $p = Piloto::where('email_piloto', $email)
            ->when(!$this->hasCol('email_piloto'), fn($q) => $q->where('email', $email))
            ->first();

        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);
        return response()->json($this->serializePiloto($p),200);
    }

    public function store(Request $r)
    {
        // Permitir nome vindo como 'nome' ou 'nome_piloto'
        $cpf   = $this->getFirst($r, ['cpf_piloto','cpf','id_piloto']);
        $nome  = $this->getFirst($r, ['nome_piloto','nome']);
        $email = $this->getFirst($r, ['email_piloto','email']);

        $v = Validator::make(
            ['cpf_piloto' => $cpf, 'nome_piloto' => $nome],
            ['cpf_piloto' => 'required|string', 'nome_piloto' => 'required|string']
        );
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        if (Piloto::where('cpf_piloto', $cpf)->exists()) {
            return response()->json(['message'=>'Piloto com esse CPF já existe'],409);
        }

        $data = [
            'cpf_piloto' => $cpf,
            'nome_piloto' => $nome,
        ];

        // telefone
        $data['numero_telefone'] = $this->getFirst($r, ['numero_telefone','celular','telefone']);

        // nascimento
        $data['data_nascimento'] = $this->normalizeDate(
            $this->getFirst($r, ['data_nascimento','dtNascimento'])
        );

        // tipo sanguíneo (mantém se vier)
        $tipoSang = $this->getFirst($r, ['tipo_sanguineo','tipoSanguineo']);
        if ($tipoSang !== null && $tipoSang !== '') $data['tipo_sanguineo'] = $tipoSang;

        // contato emergência
        $data['nome_contato_seguranca']   = $this->getFirst($r, ['nome_contato_seguranca','contatoEmergencia']);
        $data['numero_contato_seguranca'] = $this->getFirst($r, ['numero_contato_seguranca','telefoneEmergencia']);

        // plano de saúde
        $data['nome_plano_saude'] = $this->getFirst($r, ['nome_plano_saude','planoSaude']);

        // email principal
        if ($email) {
            if (Schema::hasColumn('pilotos','email_piloto')) $data['email_piloto'] = $email;
            elseif (Schema::hasColumn('pilotos','email'))    $data['email'] = $email;
        }

        // endereço (se vier)
        $data['tipo_endereco'] = $this->getFirst($r, ['tipo_endereco','tipoEndereco'], 'RESIDENCIAL');
        $data['cep']           = $this->getFirst($r, ['cep']);
        $data['numero']        = $this->getFirst($r, ['numero']);
        $data['logradouro']    = $this->getFirst($r, ['logradouro']);
        $data['complemento']   = $this->getFirst($r, ['complemento']);
        $data['bairro']        = $this->getFirst($r, ['bairro']);
        $data['cidade']        = $this->getFirst($r, ['cidade']);
        $data['uf']            = strtoupper((string)$this->getFirst($r, ['uf']));
        $data['pais']          = $this->getFirst($r, ['pais'], 'Brasil');

        // foto do piloto (base64/dataURL)
        $fotoPiloto = $this->getFirst($r, ['foto_piloto','foto','avatar']);
        if ($fotoPiloto) {
            $data['foto_piloto']      = $this->persistBase64($fotoPiloto);
            $data['foto_piloto_tipo'] = $this->getFirst($r, ['foto_piloto_tipo'], 'image/jpeg');
        }

        // CNH / Termo (se enviados como base64)
        $cnh = $this->getFirst($r, ['foto_cnh']);
        if ($cnh) {
            $data['foto_cnh']      = $this->persistBase64($cnh);
            $data['foto_cnh_tipo'] = $this->getFirst($r, ['foto_cnh_tipo'], null);
        }
        $termo = $this->getFirst($r, ['termo_adesao','termo']);
        if ($termo) {
            $data['termo_adesao']      = $this->persistBase64($termo);
            $data['termo_adesao_tipo'] = $this->getFirst($r, ['termo_adesao_tipo'], 'application/pdf');
        }

        // NÃO usar estado_civil (pedido seu) — então não setamos nada.

        // id_piloto: gerar se a coluna existir e vier vazio
        if ($this->hasCol('id_piloto') && empty($r->input('id_piloto'))) {
            $data['id_piloto'] = 'P'.substr($cpf, -4).'-'.Str::upper(Str::random(6));
        }

        DB::beginTransaction();
        try {
            $piloto = Piloto::create($data);

            // criar/associar usuário (tipo USER)
            if ($email) {
                $usuario = Usuario::where('email', $email)->first();
                if (!$usuario) {
                    $default = env('DEFAULT_USER_PASSWORD','UtVLegal123!');
                    $usuario = Usuario::create([
                        'nome'                 => $nome,
                        'email'                => $email,
                        'senha'                => $default,   // mutator grava em senha_hash
                        'tipo'                 => 'USER',
                        'tipo_usuario'         => 'USER',
                        'is_active'            => true,
                        'must_change_password' => Schema::hasColumn('usuarios','must_change_password') ? true : null,
                    ]);
                }
                if ($this->hasCol('usuario_id')) {
                    $piloto->usuario_id = $usuario->id;
                } elseif ($this->hasCol('email_usuario')) {
                    $piloto->email_usuario = $usuario->email;
                }
                $piloto->save();
            }

            DB::commit();
            return response()->json(['piloto' => $this->serializePiloto($piloto)], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Piloto.store] '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message'=>'Erro interno ao criar piloto'], 500);
        }
    }

    public function update(Request $r, $cpf)
    {
        $p = Piloto::where('cpf_piloto', $cpf)->first();
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);

        // mesmo mapeamento do store, mas “parcial”
        $upd = [];

        $nome = $this->getFirst($r, ['nome_piloto','nome']);
        if ($nome) $upd['nome_piloto'] = $nome;

        $email = $this->getFirst($r, ['email_piloto','email']);
        if ($email) {
            if ($this->hasCol('email_piloto')) $upd['email_piloto'] = $email;
            elseif ($this->hasCol('email'))     $upd['email'] = $email;
        }

        $val = $this->getFirst($r, ['numero_telefone','celular','telefone']);
        if ($val) $upd['numero_telefone'] = $val;

        $val = $this->normalizeDate($this->getFirst($r, ['data_nascimento','dtNascimento']));
        if ($val) $upd['data_nascimento'] = $val;

        $val = $this->getFirst($r, ['tipo_sanguineo','tipoSanguineo']);
        if ($val !== null && $val !== '') $upd['tipo_sanguineo'] = $val;

        $val = $this->getFirst($r, ['nome_contato_seguranca','contatoEmergencia']);
        if ($val) $upd['nome_contato_seguranca'] = $val;

        $val = $this->getFirst($r, ['numero_contato_seguranca','telefoneEmergencia']);
        if ($val) $upd['numero_contato_seguranca'] = $val;

        $val = $this->getFirst($r, ['nome_plano_saude','planoSaude']);
        if ($val) $upd['nome_plano_saude'] = $val;

        // endereço
        foreach ([
            'tipo_endereco' => ['tipo_endereco','tipoEndereco'],
            'cep'           => ['cep'],
            'numero'        => ['numero'],
            'logradouro'    => ['logradouro'],
            'complemento'   => ['complemento'],
            'bairro'        => ['bairro'],
            'cidade'        => ['cidade'],
            'uf'            => ['uf'],
            'pais'          => ['pais'],
        ] as $dbCol => $keys) {
            $val = $this->getFirst($r, $keys);
            if ($val !== null && $val !== '') {
                $upd[$dbCol] = $dbCol === 'uf' ? strtoupper($val) : $val;
            }
        }

        // mídias
        $val = $this->getFirst($r, ['foto_piloto','foto','avatar']);
        if ($val) {
            $upd['foto_piloto']      = $this->persistBase64($val);
            $upd['foto_piloto_tipo'] = $this->getFirst($r, ['foto_piloto_tipo'], 'image/jpeg');
        }
        $val = $this->getFirst($r, ['foto_cnh']);
        if ($val) {
            $upd['foto_cnh']      = $this->persistBase64($val);
            $upd['foto_cnh_tipo'] = $this->getFirst($r, ['foto_cnh_tipo'], null);
        }
        $val = $this->getFirst($r, ['termo_adesao','termo']);
        if ($val) {
            $upd['termo_adesao']      = $this->persistBase64($val);
            $upd['termo_adesao_tipo'] = $this->getFirst($r, ['termo_adesao_tipo'], 'application/pdf');
        }

        $p->update($upd);
        return response()->json($this->serializePiloto($p->fresh()),200);
    }

    public function destroy($cpf)
    {
        $p = Piloto::where('cpf_piloto',$cpf)->first();
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);
        $p->delete();
        return response()->json(['message'=>'Piloto removido com sucesso'],200);
    }

    /* uploads multipart continuam (caso use no front) */

    public function uploadCnh(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message'=>'Nenhum arquivo enviado'],400);

        $p = Piloto::where('cpf_piloto',$cpf)->first();
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);

        if ($this->hasCol('foto_cnh'))      $p->foto_cnh = $publicPath;
        if ($this->hasCol('foto_cnh_tipo')) $p->foto_cnh_tipo = $file->getClientMimeType();
        $p->save();

        return response()->json(['message'=>'CNH enviada','path'=>$publicPath],200);
    }

    public function uploadTermo(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message'=>'Nenhum arquivo enviado'],400);

        $p = Piloto::where('cpf_piloto',$cpf)->first();
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);

        if ($this->hasCol('termo_adesao'))      $p->termo_adesao = $publicPath;
        if ($this->hasCol('termo_adesao_tipo')) $p->termo_adesao_tipo = $file->getClientMimeType();
        $p->save();

        return response()->json(['message'=>'Termo enviado','path'=>$publicPath],200);
    }
}
