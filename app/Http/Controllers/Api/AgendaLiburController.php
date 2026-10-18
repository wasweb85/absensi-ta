<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use App\Models\HariLibur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaLiburController extends Controller
{
    // ==========================================
    // AGENDA
    // ==========================================
    public function getAgenda(): JsonResponse
    {
        $agenda = Agenda::orderBy('tanggal', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $agenda,
        ]);
    }

    public function addAgenda(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal' => 'required|date',
            'judul' => 'required|string|max:200',
            'keterangan' => 'nullable|string',
        ]);

        $agenda = Agenda::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil ditambahkan',
            'data' => $agenda,
        ], 201);
    }

    public function updateAgenda(Request $request, int $id): JsonResponse
    {
        $agenda = Agenda::findOrFail($id);

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'judul' => 'required|string|max:200',
            'keterangan' => 'nullable|string',
        ]);

        $agenda->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil diperbarui',
            'data' => $agenda,
        ]);
    }

    public function deleteAgenda(int $id): JsonResponse
    {
        $agenda = Agenda::findOrFail($id);
        $agenda->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil dihapus',
        ]);
    }

    // ==========================================
    // HARI LIBUR
    // ==========================================
    public function getLibur(): JsonResponse
    {
        $libur = HariLibur::orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $libur,
        ]);
    }

    public function addLibur(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'keterangan' => 'required|string|max:200',
        ]);

        $libur = HariLibur::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan',
            'data' => $libur,
        ], 201);
    }

    public function deleteLibur(int $id): JsonResponse
    {
        $libur = HariLibur::findOrFail($id);
        $libur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus',
        ]);
    }
}
