<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CarroController extends Controller
{
    protected function storageUrlToBase64(?string $storageUrl): ?string
    {
        if (!$storageUrl || !is_string($storageUrl)) return null;
        if (strpos($storageUrl, '/storage/') !== 0) return $storageUrl;

        $relative = ltrim(str_replace('/storage/', '', $storageUrl), '/');
        if (!Storage::disk('public')->exists($relative)) return null;
        $bytes = Storage::disk('public')->get($relative);
        return base64_encode($bytes);
    }

    protected function saveBase64ToPublic(string $base64, string $relative): string
    {
        Storage::disk('public')->put($relative, base64_decode($base64));
        return Storage::url($relative);
    }

    protected function persistMaybeBase64(?string $value, string $destRelative): ?string
    {
        if (!$value) return null;
        if (str_contains($value, '/storage/')) return $value;

        if (str_contains($value, ',')) {
            $parts = explode(',', $value, 2);
            $value = end($parts);
        }

        $isLikelyBase64 = (bool) preg_match('/^[A-Za-z0-9+\/=]+$/', $value);
        if ($isLikelyBase64) {
            return $this->saveBase64ToPublic($value, $destRelative);
        }

        return $value;
    }

    // GET /api/carros/{cpf}
    public function show($cpf)
    {
        $carro = Carro::where('cpf_piloto', $cpf)->first();
        if (!$carro) return response()->json(['message' => 'Carro não encontrado'], 404);

        $data = $carro->toArray();

        foreach (['foto_frente','foto_tras','foto_esquerda','foto_direita','nota_fiscal'] as $campo) {
            if (Schema::hasColumn('carros', $campo)) {
                $data[$campo] = $this->storageUrlToBase64($carro->$campo);
            }
        }

        // Nota fiscal pode ser imagem ou PDF; o front já abre como PDF, então ok
        return response()->json($data, 200);
    }

    // POST/PUT /api/carros/{cpf} — usados pelo front
    public function store(Request $r, $cpf = null)
    {
        $cpf = $cpf ?: $r->input('cpf_piloto');
        if (!$cpf) return response()->json(['message' => 'cpf_piloto obrigatório'], 422);

        $carro = Carro::firstOrNew(['cpf_piloto' => $cpf]);

        // salvar arquivos se vierem (base64)
        $carro->foto_frente   = $this->persistMaybeBase64($r->input('foto_frente'),   "carros/{$cpf}/frente.jpg")   ?: $carro->foto_frente;
        $carro->foto_tras     = $this->persistMaybeBase64($r->input('foto_tras'),     "carros/{$cpf}/tras.jpg")     ?: $carro->foto_tras;
        $carro->foto_esquerda = $this->persistMaybeBase64($r->input('foto_esquerda'), "carros/{$cpf}/esquerda.jpg") ?: $carro->foto_esquerda;
        $carro->foto_direita  = $this->persistMaybeBase64($r->input('foto_direita'),  "carros/{$cpf}/direita.jpg")  ?: $carro->foto_direita;

        // nota fiscal pode ser PDF ou imagem; vamos usar .pdf por padrão
        if ($r->filled('nota_fiscal')) {
            $carro->nota_fiscal = $this->persistMaybeBase64($r->input('nota_fiscal'), "carros/{$cpf}/nota_fiscal.pdf");
        }

        $carro->save();
        return $this->show($cpf);
    }

    public function update(Request $r, $cpf)
    {
        return $this->store($r, $cpf);
    }
}
