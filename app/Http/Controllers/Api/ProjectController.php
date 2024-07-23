<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    #[OA\Get(
        summary: 'List',
        description: 'list projects.',
        path: '/projects',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all projects.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Project')
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
        ]
    )]
    public function projects(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $projects = Project::whereTeamId($teamId)->select('id', 'name', 'uuid')->get();

        return response()->json(serializeApiResponse($projects),
        );
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get project by Uuid.',
        path: '/projects/{uuid}',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project details',
                content: new OA\JsonContent(ref: '#/components/schemas/Project')),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                description: 'Project not found.',
            ),
        ]
    )]
    public function project_by_uuid(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $project = Project::whereTeamId($teamId)->whereUuid(request()->uuid)->first()->load(['environments']);
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        return response()->json(
            serializeApiResponse($project),
        );
    }

    #[OA\Get(
        summary: 'Environment',
        description: 'Get environment by name.',
        path: '/projects/{uuid}/{environment_name}',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Project UUID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'environment_name', in: 'path', required: true, description: 'Environment name', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project details',
                content: new OA\JsonContent(ref: '#/components/schemas/Environment')),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function environment_details(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        if (! $request->uuid) {
            return response()->json(['message' => 'Uuid is required.'], 422);
        }
        if (! $request->environment_name) {
            return response()->json(['message' => 'Environment name is required.'], 422);
        }
        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        $environment = $project->environments()->whereName($request->environment_name)->first();
        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }
        $environment = $environment->load(['applications', 'postgresqls', 'redis', 'mongodbs', 'mysqls', 'mariadbs', 'services']);

        return response()->json(serializeApiResponse($environment));
    }

    #[OA\Post(
        summary: 'Create Project',
        description: 'Create Project.',
        path: '/projects',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Project created.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'name' => ['type' => 'string', 'description' => 'The name of the project.'],
                        'description' => ['type' => 'string', 'description' => 'The description of the project.'],
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project created.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'uuid' => ['type' => 'string', 'example' => 'og888os'],
                                'name' => ['type' => 'string', 'example' => 'Project Name'],
                                'description' => ['type' => 'string', 'example' => 'Project Description'],
                            ]
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function create_project(Request $request)
    {
        $allowedFields = ['name', 'description'];

        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $project = Project::create([
            'name' => $request->name,
            'description' => $request->description,
            'team_id' => $teamId,
        ]);

        return response()->json([
            'uuid' => $project->uuid,
            'name' => $project->name,
            'description' => $project->description,
        ])->status(201);
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete project by UUID.',
        path: '/projects/{uuid}',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the application.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Project deleted.'],
                            ]
                        )
                    ),
                ]),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function delete_project(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        if (! $request->uuid) {
            return response()->json(['message' => 'Uuid is required.'], 422);
        }
        $project = Project::whereTeamId($teamId)->whereUuid($request->uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }
        if ($project->resource_count() > 0) {
            return response()->json(['message' => 'Project has resources, so it cannot be deleted.'], 400);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }
}
