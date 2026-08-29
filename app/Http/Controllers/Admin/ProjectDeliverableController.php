<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectDeliverableController extends Controller
{
    public function store(Project $project, Request $request): JsonResponse
    {
        $data=$request->validate(['name'=>['required','string','max:255'],'description'=>['nullable','string'],'category'=>['nullable','string','max:100'],'requirement_level'=>['required','in:required,recommended,optional'],'expected_resource_type'=>['required','in:file,folder,document,guide,service_account,external_link,manual_confirmation'],'client_visible'=>['required','boolean']]);
        $item=$project->deliverables()->create(['key_snapshot'=>'adhoc_'.uniqid(),'name_snapshot'=>$data['name'],'description_snapshot'=>$data['description']??null,'category_snapshot'=>$data['category']??null,'requirement_level'=>$data['requirement_level'],'expected_resource_type'=>$data['expected_resource_type'],'client_visible'=>$data['client_visible'],'sort_order'=>(int)$project->deliverables()->max('sort_order')+1]);
        return response()->json($item,201);
    }
    public function update(Project $project, ProjectDeliverable $deliverable, Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($deliverable->project_id===$project->id,404);
        $data=$request->validate(['status'=>['required','in:pending,in_progress,completed,waived'],'notes'=>['nullable','string','max:5000'],'sort_order'=>['nullable','integer','min:0']]);
        if($data['status']==='waived'&&$deliverable->requirement_level==='required') abort(422,'Required deliverables cannot be waived.');
        $deliverable->update($data+['completed_at'=>$data['status']==='completed'?now():null,'completed_by'=>$data['status']==='completed'?$request->user()->id:null]);
        if(in_array($data['status'],['completed','waived'],true))$audit->record('project_deliverable_'.$data['status'],$request->user(),$deliverable,$project->company_id,$project->id);
        return response()->json($deliverable);
    }
}