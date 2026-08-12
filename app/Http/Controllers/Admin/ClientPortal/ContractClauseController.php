<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ContractClause;
use App\Models\ContractClauseVersion;
use App\Services\ContractBlockDocumentService;
use App\Services\ContractClauseVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractClauseController extends Controller
{
    public function index(Request $request): JsonResponse { return response()->json(ContractClause::query()->with(['versions'=>fn($q)=>$q->where('status','published')->latest('published_at')])->when($request->filled('search'),fn($q)=>$q->where('name','like','%'.$request->string('search').'%'))->orderBy('category')->orderBy('name')->get()); }
    public function store(Request $request, ContractClauseVersionService $service): JsonResponse { $data=$request->validate(['name'=>['required','string','max:255'],'category'=>['required','string','max:100'],'version'=>['required','regex:/^\d+\.\d+$/']]);$clause=ContractClause::query()->create($data);return response()->json($service->createDraft($clause,$data['version'],$request->user())->load('clause'),201); }
    public function draft(ContractClause $clause, Request $request, ContractClauseVersionService $service): JsonResponse { $data=$request->validate(['version'=>['required','regex:/^\d+\.\d+$/']]);return response()->json($service->createDraft($clause,$data['version'],$request->user()),201); }
    public function update(ContractClauseVersion $version, Request $request, ContractBlockDocumentService $documents): JsonResponse { abort_unless($version->status==='draft',409);$data=$request->validate(['content'=>['required','array']]);$version->update(['content'=>$documents->validate($data['content'])]);return response()->json($version); }
    public function publish(ContractClauseVersion $version, Request $request, ContractClauseVersionService $service): JsonResponse { $data=$request->validate(['change_summary'=>['required','string','max:5000']]);return response()->json($service->publish($version,$data['change_summary'],$request->user())); }
}