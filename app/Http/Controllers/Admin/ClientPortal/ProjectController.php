<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientPortal\StoreProjectRequest;
use App\Http\Resources\Admin\ClientPortal\ProjectResource;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use App\Services\ProjectInstantiationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = in_array($request->string('sort')->toString(), ['name', 'portal_status', 'updated_at'], true)
            ? $request->string('sort')->toString() : 'updated_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $search = trim($request->string('search')->toString());

        return ProjectResource::collection(Project::query()
            ->whereNotNull('company_id')
            ->with(['company', 'serviceProduct'])
            ->withCount('contacts')
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('project_code', 'like', "%{$search}%")->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%{$search}%"))))
            ->when($request->filled('status'), fn ($query) => $query->where('portal_status', $request->string('status')))
            ->when($request->integer('company_id'), fn ($query, $id) => $query->where('company_id', $id))
            ->when($request->integer('service_product_id'), fn ($query, $id) => $query->where('service_product_id', $id))
            ->orderBy($sort, $direction)
            ->paginate(min(max($request->integer('per_page', 25), 10), 100)));
    }

    public function store(StoreProjectRequest $request, ProjectInstantiationService $service): JsonResponse
    {
        $project = $service->create($request->validated(), $request->user());

        return (new ProjectResource($project->load(['company', 'serviceProduct', 'contacts', 'coworkers'])->loadCount('contacts')))
            ->response()->setStatusCode(201);
    }

    public function show(Project $project): ProjectResource
    {
        abort_if($project->company_id === null, 404);

        return new ProjectResource($project->load(['company', 'serviceProduct', 'blueprintVersion.blueprint', 'contacts', 'coworkers', 'deliverables', 'folders', 'contracts.templateVersion.template'])->loadCount('contacts'));
    }

    public function update(StoreProjectRequest $request, Project $project): ProjectResource
    {
        $currentContactIds = $project->contacts()->pluck('client_contacts.id')->map(fn ($id) => (int) $id)->all();
        $currentCoworkerIds = $project->coworkers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $currentAdminId = $request->user()?->is_admin ? (int) $request->user()->id : null;

        $nextContactIds = collect($request->validated('contact_ids', []))->map(fn ($id) => (int) $id)->all();
        $nextCoworkerIds = collect($request->validated('coworker_ids', []))->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();

        if ($currentAdminId) {
            $nextCoworkerIds = $nextCoworkerIds->push($currentAdminId)->unique()->values();
        }

        $nextCoworkerIds = $nextCoworkerIds->all();

        $addedContactIds = array_values(array_diff($nextContactIds, $currentContactIds));
        $addedCoworkerIds = array_values(array_diff($nextCoworkerIds, $currentCoworkerIds));

        DB::transaction(function () use ($request, $project, $nextCoworkerIds) {
            $data = $request->safe()->except(['contact_ids', 'coworker_ids']);
            $data['url'] = isset($data['url']) && $data['url'] !== ''
                ? $data['url']
                : $project->url;
            $project->update($data);
            $project->contacts()->sync($request->validated('contact_ids', []));
            $project->coworkers()->sync($nextCoworkerIds);
            if ($project->portal_status !== 'archived') {
                $project->update(['archived_at' => null]);
            }
        });

        if (! empty($addedContactIds)) {
            ClientContact::query()->whereIn('id', $addedContactIds)->update([
                'active' => true,
                'can_access_portal' => true,
                'access_revoked_at' => null,
            ]);
        }

        DB::afterCommit(function () use ($project, $addedContactIds, $addedCoworkerIds) {
            if (! empty($addedContactIds)) {
                $contacts = ClientContact::query()->whereIn('id', $addedContactIds)->get();
                foreach ($contacts as $contact) {
                    $contact->notify(new ProjectInvitationNotification($project, route('client.login')));
                }
            }

            if (! empty($addedCoworkerIds)) {
                $coworkers = User::query()->whereIn('id', $addedCoworkerIds)->where('is_admin', false)->get();
                foreach ($coworkers as $coworker) {
                    $coworker->notify(new ProjectInvitationNotification($project, route('login')));
                }
            }
        });

        return new ProjectResource($project->fresh()->load(['company', 'serviceProduct', 'contacts', 'coworkers'])->loadCount('contacts'));
    }

    public function archive(Project $project): Response
    {
        $project->update(['portal_status' => 'archived', 'archived_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Project $project): Response
    {
        DB::transaction(function () use ($project) {
            $contractIds = $project->contracts()->pluck('id');

            if ($contractIds->isNotEmpty()) {
                DB::table('contract_acceptances')
                    ->whereIn('contract_instance_id', $contractIds)
                    ->delete();

                $project->contracts()->delete();
            }

            $priceOfferIds = $project->priceOffers()->pluck('id');

            if ($priceOfferIds->isNotEmpty()) {
                DB::table('price_offer_acceptances')
                    ->whereIn('price_offer_id', $priceOfferIds)
                    ->delete();

                $project->priceOffers()->delete();
            }

            DB::table('project_documents')
                ->where('project_id', $project->id)
                ->delete();

            $project->files()->delete();
            $project->contacts()->detach();
            $project->coworkers()->detach();

            $project->delete();
        });

        return response()->noContent();
    }

    public function publish(Project $project, Request $request): ProjectResource
    {
        $data = $request->validate(['is_published' => ['required', 'boolean']]);
        $project->update($data);
        return new ProjectResource($project->fresh()->load(['company', 'serviceProduct']));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $suffix = 2;
        while (Project::query()->where('url', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}