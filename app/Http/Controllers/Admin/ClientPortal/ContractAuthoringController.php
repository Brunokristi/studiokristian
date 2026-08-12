<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ServiceProduct;
use App\Services\ContractBlockDocumentService;
use App\Services\ContractTemplateVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractAuthoringController extends Controller
{
    public function createTemplate(ServiceProduct $serviceProduct, Request $request): JsonResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255']]);
        $template=ContractTemplate::query()->create(['service_product_id'=>$serviceProduct->id,'name'=>$data['name'],'slug'=>Str::slug($data['name']).'-'.$serviceProduct->id]);
        $serviceProduct->update(['default_contract_template_id'=>$template->id]);
        return response()->json($template,201);
    }
    public function draft(ContractTemplate $template, Request $request, ContractTemplateVersionService $service): JsonResponse
    {
        $data=$request->validate(['version'=>['required','regex:/^\d+\.\d+$/']]);
        return response()->json($service->createDraft($template,$data['version'],$request->user()),201);
    }
    public function update(ContractTemplateVersion $version, Request $request, ContractBlockDocumentService $documents): JsonResponse
    {
        abort_unless($version->status==='draft',409);
        $data=$request->validate(['document_schema'=>['required','array'],'field_definitions'=>['array'],'field_definitions.*.key'=>['required','regex:/^[a-z][a-z0-9_]*$/'],'field_definitions.*.label'=>['required','string'],'field_definitions.*.type'=>['required','in:text,textarea,number,date,checkbox,select,multi_select,radio'],'field_definitions.*.required'=>['required','boolean'],'field_definitions.*.options'=>['nullable','array']]);
        $version->update(['document_schema'=>$documents->validate($data['document_schema']),'field_definitions'=>$data['field_definitions']??[]]);
        return response()->json($version);
    }
    public function publish(ContractTemplateVersion $version, Request $request, ContractTemplateVersionService $service): JsonResponse
    {
        $data=$request->validate(['change_summary'=>['required','string','max:5000']]);
        return response()->json($service->publish($version,'future_only',$data['change_summary'],$request->user()));
    }
}