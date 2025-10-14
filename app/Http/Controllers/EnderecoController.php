<?php

namespace App\Http\Controllers;

use App\Models\Endereco;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class EnderecoController extends Controller
{
    // GET /endereco/{cpf}
    public function show($cpf)
    {
        $endereco = Endereco::where('cpf_piloto', $cpf)->first();
        if (!$endereco) {
            return response()->json(['message' => 'Endereço não encontrado'], 404);
        }
        return response()->json($endereco, 200);
    }

    // POST /endereco or POST /endereco/{cpf}
    public function store(Request $request, $cpf = null)
    {
        Log::debug('Endereco.store payload:', $request->all());

        $data = $request->all();
        $data['cpf_piloto'] = $cpf ?? ($data['cpf_piloto'] ?? null);

        $validator = Validator::make($data, [
            'cpf_piloto' => 'required|string',
            'tipo_endereco' => 'nullable|string|max:50',
            'cep' => 'nullable|string|max:20',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|numeric',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:5',
            'pais' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Se já existir um registro para esse CPF, retornar 409 (ou você pode optar por atualizar)
            $exists = Endereco::where('cpf_piloto', $data['cpf_piloto'])->first();
            if ($exists) {
                // Retornar existente? Atualizar? Aqui seguimos: criar retorna 409
                return response()->json(['message' => 'Endereço já cadastrado para este CPF'], 409);
            }

            $endereco = Endereco::create($data);
            return response()->json($endereco, 201);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar Endereco: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao criar endereço'], 500);
        }
    }

    // PUT /endereco/{cpf}
    public function update(Request $request, $cpf)
    {
        Log::debug("Endereco.update payload for cpf {$cpf}:", $request->all());

        $endereco = Endereco::where('cpf_piloto', $cpf)->first();
        if (!$endereco) {
            return response()->json(['message' => 'Endereço não encontrado'], 404);
        }

        $data = $request->all();
        // Não permitir alterar cpf via update por segurança (mas se quiser, permite)
        unset($data['cpf_piloto']);

        $validator = Validator::make($data, [
            'tipo_endereco' => 'nullable|string|max:50',
            'cep' => 'nullable|string|max:20',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|numeric',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|max:5',
            'pais' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $endereco->update($data);
            return response()->json($endereco, 200);
        } catch (\Throwable $e) {
            Log::error('Erro ao atualizar Endereco: '.$e->getMessage());
            return response()->json(['message' => 'Erro interno ao atualizar endereço'], 500);
        }
    }
}
