<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'judul' => 'required|string|max:200',
            'kategori' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);

        if (empty($validated['tanggal_selesai'])) {
            $validated['tanggal_selesai'] = $validated['tanggal'];
        }
        if (empty($validated['kategori'])) {
            $validated['kategori'] = 'Umum';
        }

        $agenda = Agenda::create($validated);

        ActivityLog::record(
            modul: 'agenda',
            aksi: 'tambah',
            deskripsi: "Menambahkan agenda baru: {$agenda->judul} ({$agenda->tanggal})",
            properties: [
                'id_agenda' => $agenda->id,
                'judul' => $agenda->judul,
                'tanggal' => $agenda->tanggal,
            ]
        );

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
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'judul' => 'required|string|max:200',
            'kategori' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);

        if (empty($validated['tanggal_selesai'])) {
            $validated['tanggal_selesai'] = $validated['tanggal'];
        }
        if (empty($validated['kategori'])) {
            $validated['kategori'] = 'Umum';
        }

        $agenda->update($validated);

        ActivityLog::record(
            modul: 'agenda',
            aksi: 'ubah',
            deskripsi: "Memperbarui agenda: {$agenda->judul}",
            properties: [
                'id_agenda' => $agenda->id,
                'judul' => $agenda->judul,
                'tanggal' => $agenda->tanggal,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil diperbarui',
            'data' => $agenda,
        ]);
    }

    public function deleteAgenda(int $id): JsonResponse
    {
        $agenda = Agenda::findOrFail($id);
        $judul = $agenda->judul;
        $agenda->delete();

        ActivityLog::record(
            modul: 'agenda',
            aksi: 'hapus',
            deskripsi: "Menghapus agenda: {$judul}",
            properties: [
                'id_agenda' => $id,
                'judul' => $judul,
            ]
        );

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

        ActivityLog::record(
            modul: 'libur',
            aksi: 'tambah',
            deskripsi: "Menambahkan jadwal hari libur: {$libur->keterangan} ({$libur->tanggal_mulai} s.d. {$libur->tanggal_selesai})",
            properties: [
                'id_libur' => $libur->id,
                'keterangan' => $libur->keterangan,
                'tanggal_mulai' => $libur->tanggal_mulai,
                'tanggal_selesai' => $libur->tanggal_selesai,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil ditambahkan',
            'data' => $libur,
        ], 201);
    }

    public function deleteLibur(int $id): JsonResponse
    {
        $libur = HariLibur::findOrFail($id);
        $keterangan = $libur->keterangan;
        $libur->delete();

        ActivityLog::record(
            modul: 'libur',
            aksi: 'hapus',
            deskripsi: "Menghapus jadwal hari libur: {$keterangan}",
            properties: [
                'id_libur' => $id,
                'keterangan' => $keterangan,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Hari libur berhasil dihapus',
        ]);
    }
}
