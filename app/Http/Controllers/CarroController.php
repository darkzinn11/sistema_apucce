<?php

namespace App\Http\Controllers;

use App\Models\Carro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CarroController extends Controller
{
    // Helper: salva arquivo multipart ou base64 e retorna public path (ou null)
    protected function saveFileFromRequest(Request $request, $field, $cpf, $dir = 'carros')
    {
        // prioridade: file upload via multipart
        if ($request->hasFile($field) && $request->file($field)->isValid()) {
            $file = $request->file($field);
            $path = $file->store("public/{$dir}/{$cpf}");
            return Storage::url($path);
        }

        // fallback: campo textual base64 (data:[<mime>];base64,<data> or raw base64)
        $val = $request->input($field);
        if (!$val) return null;

        // if it's data URI like data:...;base64,xxxx
        if (preg_match('/^data:(.*);base64,(.*)$/', $val, $matches)) {
            $mime = $matches[1];
            $b64  = $matches[2];
        } else {
            // assume raw base64 and try to detect type
            $b64 = $val;
            $mime = null;
        }

        try {
            $bytes = base64_decode($b64);
            if ($bytes === false) return null;

            // try to detect extension from mime or default to jpg
            $ext = 'jpg';
            if ($mime) {
                if (strpos($mime, 'pdf') !== false) $ext = 'pdf';
                elseif (strpos($mime, 'png') !== false) $ext = 'png';
                elseif (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) $ext = 'jpg';
            }

            $filename = uniqid($field . '_') . '.' . $ext;
            $path = "public/{$dir}/{$cpf}/{$filename}";
            Storage::put($path, $bytes);
            return Storage::url($path);
        } catch (\Throwable $e) {
            Log::warning("Fail saving base64 {$field}: ".$e->getMessage());
            return null;
        }
    }

    // GET /carros/{cpf}
    public function show($cpf)
    {
        $carro = Carro::where('cpf_piloto', $cpf)->first();
        if (!$carro) {
            return response()->json(['message' => 'Carro não encontrado'], 404);
        }
        return response()->json($carro, 200);
    }

    // POST /carros or POST /carros/{cpf}
    public function store(Request $request, $cpf = null)
    {
        Log::debug('Carro.store payload (non-file fields):', $request->except(['foto_frente','foto_tras','foto_esquerda','foto_direita','nota_fiscal']));
        $data = $request->all();
        $data['cpf_piloto'] = $cpf ?? ($data['cpf_piloto'] ?? null);

        $validator = Validator::make($data, [
            'cpf_piloto' => 'required|string',
            // fotos são opcionais
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // checar duplicidade
            $exists = Carro::where('cpf_piloto', $data['cpf_piloto'])->first();
            if ($exists) {
                return response()->json(['message' => 'Registro de veículo já existe para esse CPF'], 409);
            }

            $record = [
                'cpf_piloto' => $data['cpf_piloto'],
            ];

            // salvar arquivos se existirem (tenta multipart/form-data primeiro)
            $fields = ['foto_frente','foto_tras','foto_esquerda','foto_direita','nota_fiscal'];
            foreach ($fields as $f) {
                $saved = $this->saveFileFromRequest($request, $f, $data['cpf_piloto']);
                if ($saved) {
                    // grava somente se a coluna existir
                    if (Schema::hasColumn('carros', $f)) {
                        $record[$f] = $saved;
                    }
                }
            }

            $carro = Carro::create($record);
            return response()->json($carro, 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar Carro: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao criar carro'], 500);
        }
    }

    // PUT /carros/{cpf}
    public function update(Request $request, $cpf)
    {
        Log::debug("Carro.update payload for cpf {$cpf}:", $request->except(['foto_frente','foto_tras','foto_esquerda','foto_direita','nota_fiscal']));

        $carro = Carro::where('cpf_piloto', $cpf)->first();
        if (!$carro) {
            return response()->json(['message' => 'Carro não encontrado'], 404);
        }

        $data = $request->all();
        unset($data['cpf_piloto']); // não troca o cpf

        // salvar/atualizar arquivos
        $fields = ['foto_frente','foto_tras','foto_esquerda','foto_direita','nota_fiscal'];
        foreach ($fields as $f) {
            $saved = $this->saveFileFromRequest($request, $f, $cpf);
            if ($saved && Schema::hasColumn('carros', $f)) {
                $carro->$f = $saved;
            }
        }

        // atualiza campos simples (se existirem colunas correspondentes)
        foreach ($data as $k => $v) {
            if (Schema::hasColumn('carros', $k) && $k !== 'cpf_piloto') {
                $carro->$k = $v;
            }
        }

        try {
            $carro->save();
            return response()->json($carro, 200);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar Carro: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao atualizar carro'], 500);
        }
    }
}
