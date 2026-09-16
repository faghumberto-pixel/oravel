<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signature;
use App\Mail\SignatureAcceptedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SignatureController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'name' => 'required|string|max:255',
            'timestamp' => 'required|date_format:Y-m-d\TH:i:s\Z',
            'hash' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validação falhou',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $signature = Signature::create([
                'company' => $request->company,
                'email' => $request->email,
                'name' => $request->name,
                'signed_at' => $request->timestamp,
                'hash' => $request->hash,
                'ip_origin' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'user_agent' => $request->userAgent(),
                    'referer' => $request->header('referer'),
                ],
            ]);

            Mail::to('suporte@oravel.com.br')->send(
                new SignatureAcceptedMail($signature)
            );

            Mail::to($signature->email)->send(
                new SignatureAcceptedMail($signature)
            );

            $signature->update(['email_sent' => true, 'email_sent_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura registrada com sucesso',
                'signature_id' => $signature->id,
                'hash' => $signature->hash,
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'unique')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assinatura já existe',
                    'errors' => ['hash' => ['Esta assinatura digital já foi registrada']],
                ], 422);
            }

            \Log::error('Erro de banco de dados ao registrar assinatura', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar assinatura',
            ], 500);

        } catch (\Exception $e) {
            \Log::error('Erro ao registrar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar assinatura',
            ], 500);
        }
    }
}
