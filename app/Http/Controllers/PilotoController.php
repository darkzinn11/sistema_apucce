<?php

namespace App\Http\Controllers;

use App\Models\Piloto;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PilotoController extends Controller
{
    /* ======================== helpers de tabela/busca ======================== */

    protected function tableColumns(): array
    {
        return Schema::hasTable('pilotos') ? Schema::getColumnListing('pilotos') : [];
    }

    protected function findByCpf($cpf): ?Piloto
    {
        foreach (['cpf_piloto', 'cpf', 'cpfPiloto', 'id_piloto'] as $col) {
            if (Schema::hasColumn('pilotos', $col)) {
                $p = Piloto::where($col, $cpf)->first();
                if ($p) return $p;
            }
        }
        return null;
    }

    protected function findByEmail(string $email): ?Piloto
    {
        foreach (['email_piloto','email'] as $c) {
            if (Schema::hasColumn('pilotos', $c)) {
                $p = Piloto::where($c, $email)->first();
                if ($p) return $p;
            }
        }
        if (Schema::hasColumn('pilotos', 'usuario_id')) {
            $u = Usuario::where('email', $email)->first();
            if ($u) return Piloto::where('usuario_id', $u->id)->first();
        }
        return null;
    }

    protected function mapPayloadToDb(array $payload): array
    {
        $cols = $this->tableColumns();
        $out = [];

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

            // mídias
            'foto_piloto' => ['foto_piloto','foto','avatar'],
            'foto_piloto_tipo' => ['foto_piloto_tipo'],
            'foto_cnh' => ['foto_cnh'],
            'foto_cnh_tipo' => ['foto_cnh_tipo'],
            'termo_adesao' => ['termo_adesao','termo'],
            'termo_adesao_tipo' => ['termo_adesao_tipo'],

            'usuario_id' => ['usuario_id'],
            'email_usuario' => ['email_usuario'],
        ];

        foreach ($aliases as $payloadKey => $dbCandidates) {
            if (!array_key_exists($payloadKey, $payload)) continue;
            foreach ($dbCandidates as $c) {
                if (in_array($c, $cols)) { $out[$c] = $payload[$payloadKey]; break; }
            }
        }

        foreach ($payload as $k => $v) {
            if (in_array($k, $cols) && !array_key_exists($k, $out)) $out[$k] = $v;
        }
        return $out;
    }

    /* ======================== helpers de mídia/data ======================== */

    // se vier "data:..." devolve só a parte base64; se vier já "cru", devolve como está
    protected function stripDataUrl(string $maybeDataUrl): string
    {
        if (str_starts_with($maybeDataUrl, 'data:')) {
            [$meta, $raw] = explode(',', $maybeDataUrl, 2);
            return $raw;
        }
        return $maybeDataUrl;
    }

    // decide a estratégia: se for base64 "cru" (sem barras/espaços), guarda base64 no banco;
    // se for data:URL/URL/caminho, salva no storage e grava o /storage/... no banco
    protected function persistMedia(string $value, string $cpf, string $baseName, ?string $mime = null): array
    {
        $raw = $this->stripDataUrl($value);

        // Heurística: se parece base64 "cru" (só A–Z a–z 0–9 + / =)
        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', substr($raw, 0, 120))) {
            return ['db' => ['mode' => 'base64', 'data' => $raw, 'mime' => $mime]];
        }

        // Caso contrário, tentar salvar arquivo
        $map = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
        $ext = $map[$mime ?? ''] ?? 'bin';

        $path = "public/pilotos/{$cpf}/{$baseName}.{$ext}";
        Storage::put($path, base64_decode($raw));
        return ['db' => ['mode' => 'path', 'data' => Storage::url($path), 'mime' => $mime]];
    }

    protected function normalizeDate(?string $value): ?string
    {
        if (!$value) return null;
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) { [$d,$m,$y] = explode('/',$value); return "{$y}-{$m}-{$d}"; }
            if (str_contains($value, 'T')) return substr($value, 0, 10);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        } catch (\Throwable $e) { Log::warning('normalizeDate falhou: '.$e->getMessage()); }
        return null;
    }

    // converte qualquer coisa (base64, /storage/...) para base64 na resposta
    protected function toBase64ForResponse(?string $stored): ?string
    {
        if (!$stored) return null;

        // já é base64 “cru”?
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', substr($stored, 100) ? substr($stored,0,100) : $stored)) {
            return $stored;
        }

        // /storage/... -> ler arquivo
        if (str_starts_with($stored, '/storage/')) {
            $rel = substr($stored, 9); // remove "/storage/"
            $full = storage_path('app/public/'.$rel);
            if (is_file($full)) return base64_encode(file_get_contents($full));
            return null;
        }

        // public/... -> ler arquivo
        if (str_starts_with($stored, 'public/')) {
            $full = storage_path('app/'.$stored);
            if (is_file($full)) return base64_encode(file_get_contents($full));
            return null;
        }

        // URL http(s) – opcional
        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            try { $bin = file_get_contents($stored); return $bin ? base64_encode($bin) : null; }
            catch (\Throwable $e) { Log::warning('Falha ao baixar mídia remota: '.$e->getMessage()); }
        }

        return null;
        }

    protected function serializePiloto(Piloto $p): array
    {
        $arr = $p->toArray();

        // evitar “-1 dia”: manda como meio-dia local
        if (!empty($arr['data_nascimento'])) {
            $ymd = substr((string)$arr['data_nascimento'], 0, 10);
            $arr['data_nascimento'] = $ymd.'T12:00:00';
        }

        // mídias em base64 para o front
        $arr['foto_piloto'] = $this->toBase64ForResponse($arr['foto_piloto'] ?? null);
        $arr['foto_cnh']    = $this->toBase64ForResponse($arr['foto_cnh'] ?? null);
        $arr['termo_adesao']= $this->toBase64ForResponse($arr['termo_adesao'] ?? null);

        return $arr;
    }

    /* ======================== endpoints ======================== */

    public function index()
    {
        return Piloto::orderBy('id','desc')->get();
    }

    public function show($cpf)
    {
        $p = $this->findByCpf($cpf);
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);
        return response()->json($this->serializePiloto($p),200);
    }

    // usado pela tela "Meus Dados"
    public function showByEmail($email)
    {
        $p = $this->findByEmail($email);
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);
        return response()->json($this->serializePiloto($p),200);
    }

    public function store(Request $request)
    {
        Log::debug('Piloto.store payload:', $request->all());

        $v = Validator::make($request->all(), [
            'cpf_piloto'  => 'required|string',
            'nome_piloto' => 'required|string',
        ]);
        if ($v->fails()) return response()->json(['errors'=>$v->errors()],422);

        $payload = $request->all();
        $payload['data_nascimento'] = $this->normalizeDate($payload['data_nascimento'] ?? null);
        $dbData = $this->mapPayloadToDb($payload);

        DB::beginTransaction();
        try {
            if ($this->findByCpf($payload['cpf_piloto'])) {
                DB::rollBack();
                return response()->json(['message'=>'Piloto com esse CPF já existe'],409);
            }

            $cpf = $payload['cpf_piloto'];

            // mídias (base64 “cru” -> guarda base64; senão -> salva arquivo)
            if (!empty($payload['foto_piloto'])) {
                $md = $this->persistMedia($payload['foto_piloto'], $cpf, 'foto_piloto', $payload['foto_piloto_tipo'] ?? 'image/jpeg');
                $dbData['foto_piloto'] = $md['db']['data'];
                $dbData['foto_piloto_tipo'] = $md['db']['mime'] ?? null;
            }
            if (!empty($payload['foto_cnh'])) {
                $md = $this->persistMedia($payload['foto_cnh'], $cpf, 'foto_cnh', $payload['foto_cnh_tipo'] ?? null);
                $dbData['foto_cnh'] = $md['db']['data'];
                $dbData['foto_cnh_tipo'] = $md['db']['mime'] ?? null;
            }
            if (!empty($payload['termo_adesao'])) {
                $md = $this->persistMedia($payload['termo_adesao'], $cpf, 'termo_adesao', $payload['termo_adesao_tipo'] ?? 'application/pdf');
                $dbData['termo_adesao'] = $md['db']['data'];
                $dbData['termo_adesao_tipo'] = $md['db']['mime'] ?? null;
            }

            $piloto = Piloto::create($dbData);

            // cria/associa usuário
            $emailToUse = $payload['email_piloto'] ?? $payload['email'] ?? null;
            $nomeToUse  = $payload['nome_piloto'] ?? $payload['nome'] ?? null;
            if ($emailToUse) {
                $usuario = Usuario::where('email',$emailToUse)->first();
                if (!$usuario) {
                    $default = env('DEFAULT_USER_PASSWORD','UtVLegal123!');
                    $usuario = Usuario::create([
                        'nome'                 => $nomeToUse ?? $emailToUse,
                        'email'                => $emailToUse,
                        'senha'                => $default,
                        'tipo'                 => 'USER',
                        'tipo_usuario'         => 'USER',
                        'is_active'            => true,
                        'must_change_password' => Schema::hasColumn('usuarios','must_change_password') ? true : null,
                    ]);
                }
                if (Schema::hasColumn('pilotos','usuario_id')) {
                    $piloto->usuario_id = $usuario->id; $piloto->save();
                } elseif (Schema::hasColumn('pilotos','email_usuario')) {
                    $piloto->email_usuario = $usuario->email; $piloto->save();
                }
            }

            DB::commit();
            return response()->json(['piloto'=>$this->serializePiloto($piloto)],201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Erro ao criar Piloto: '.$e->getMessage(), ['exception'=>$e]);
            return response()->json(['message'=>'Erro interno ao criar piloto','detail'=>$e->getMessage()],500);
        }
    }

    public function update(Request $request, $cpf)
    {
        Log::debug("Piloto.update payload for cpf {$cpf}:", $request->all());

        $piloto = $this->findByCpf($cpf);
        if (!$piloto) return response()->json(['message'=>'Piloto não encontrado'],404);

        $payload = $request->all();
        $payload['data_nascimento'] = $this->normalizeDate($payload['data_nascimento'] ?? null);
        $dbData = $this->mapPayloadToDb($payload);

        if (!empty($payload['foto_piloto'])) {
            $md = $this->persistMedia($payload['foto_piloto'], $cpf, 'foto_piloto', $payload['foto_piloto_tipo'] ?? 'image/jpeg');
            $dbData['foto_piloto'] = $md['db']['data'];
            $dbData['foto_piloto_tipo'] = $md['db']['mime'] ?? null;
        }
        if (!empty($payload['foto_cnh'])) {
            $md = $this->persistMedia($payload['foto_cnh'], $cpf, 'foto_cnh', $payload['foto_cnh_tipo'] ?? null);
            $dbData['foto_cnh'] = $md['db']['data'];
            $dbData['foto_cnh_tipo'] = $md['db']['mime'] ?? null;
        }
        if (!empty($payload['termo_adesao'])) {
            $md = $this->persistMedia($payload['termo_adesao'], $cpf, 'termo_adesao', $payload['termo_adesao_tipo'] ?? 'application/pdf');
            $dbData['termo_adesao'] = $md['db']['data'];
            $dbData['termo_adesao_tipo'] = $md['db']['mime'] ?? null;
        }

        $piloto->update(array_filter($dbData, fn($v) => $v !== null));
        return response()->json($this->serializePiloto($piloto->fresh()),200);
    }

    public function destroy($cpf)
    {
        $p = $this->findByCpf($cpf);
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);
        $p->delete();
        return response()->json(['message'=>'Piloto removido com sucesso'],200);
    }

    /* uploads diretos continuam válidos (frontend multipart) */
    public function uploadCnh(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message'=>'Nenhum arquivo enviado'],400);

        $p = $this->findByCpf($cpf);
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);

        if (Schema::hasColumn('pilotos','foto_cnh')) $p->foto_cnh = $publicPath;
        if (Schema::hasColumn('pilotos','foto_cnh_tipo')) $p->foto_cnh_tipo = $file->getClientMimeType();
        $p->save();

        return response()->json(['message'=>'CNH enviada','path'=>$publicPath],200);
    }

    public function uploadTermo(Request $request, $cpf)
    {
        $file = $request->file('file');
        if (!$file) return response()->json(['message'=>'Nenhum arquivo enviado'],400);

        $p = $this->findByCpf($cpf);
        if (!$p) return response()->json(['message'=>'Piloto não encontrado'],404);

        $path = $file->store("public/pilotos/{$cpf}");
        $publicPath = Storage::url($path);

        if (Schema::hasColumn('pilotos','termo_adesao')) $p->termo_adesao = $publicPath;
        if (Schema::hasColumn('pilotos','termo_adesao_tipo')) $p->termo_adesao_tipo = $file->getClientMimeType();
        $p->save();

        return response()->json(['message'=>'Termo enviado','path'=>$publicPath],200);
    }
}
