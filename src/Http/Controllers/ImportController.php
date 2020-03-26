<?php

namespace SimonHamp\LaravelNovaCsvImport\Http\Controllers;

use Laravel\Nova\Nova;
use Laravel\Nova\Resource;
use Laravel\Nova\Rules\Relatable;
use Laravel\Nova\Http\Requests\NovaRequest;
use SimonHamp\LaravelNovaCsvImport\Importer;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\Field;

class ImportController
{
    /**
     * @var Importer
     */
    protected $importer;

    public function __construct()
    {
        $class = config('laravel-nova-csv-import.importer');

        $this->importer = new $class;
    }

    public function preview(NovaRequest $request, $file)
    {
        $import = $this->importer
            ->toCollection($this->getFilePath($file), null)
            ->first();

        $headings = $import->first()->keys();

        $total_rows = $import->count();

        $sample = $import->take(10)->all();

        $resources = collect(Nova::$resources);

        $resources = $resources->filter(function ($resource) {
            $static_vars = (new \ReflectionClass((string) $resource))->getStaticProperties();

            if (!isset($static_vars['canImportResource'])) {
                return true;
            }

            return isset($static_vars['canImportResource']) && $static_vars['canImportResource'];
        });

        $fields = $resources->map(function ($resource) {
            $model = $resource::$model;

            return new $resource(new $model);
        })->mapWithKeys(function (Resource $resource) use ($request) {
            $fields = collect($resource->creationFields($request))
                ->map(function (Field $field) {
                    return [
                        'name' => $field->name,
                        'attribute' => $field->attribute
                    ];
                });
            return [$resource->uriKey() => $fields];
        });

        $resources = $resources->mapWithKeys(function ($resource) {
            return [$resource::uriKey() => $resource::label()];
        });

        return response()->json(compact('sample', 'resources', 'fields', 'total_rows', 'headings'));
    }

    public function import(NovaRequest $request, $file)
    {
        $use_validation = $request->input('use_validation');

        $resource_name = $request->input('resource');
        $request->route()->setParameter('resource', $resource_name);

        $resource = Nova::resourceInstanceForKey($resource_name);
        $attribute_map = $request->input('mappings');
        $attributes = $resource->creationFields($request)->pluck('attribute');
        if ($use_validation === true) {
            $rules = $this->extractValidationRules($request, $resource)->toArray();
        } else {
            $rules = [];
        }
        $model_class = get_class($resource->resource);

        $this->importer
            ->setResource($resource)
            ->setAttributes($attributes)
            ->setAttributeMap($attribute_map)
            ->setRules($rules)
            ->setModelClass($model_class)
            ->import($this->getFilePath($file), null);

        if (!$this->importer->failures()->isEmpty() || !$this->importer->errors()->isEmpty()) {
            return response()->json(['result' => 'failure', 'errors' => $this->importer->errors(), 'failures' => $this->importer->failures()]);
        }

        return response()->json(['result' => 'success']);
    }

    protected function extractValidationRules($request, Resource $resource)
    {
        return collect($resource::rulesForCreation($request))->mapWithKeys(function ($rule, $key) {
            foreach ($rule as $i => $r) {
                if (!is_object($r)) {
                    continue;
                }

                // Make sure relation checks start out with a clean query
                if (is_a($r, Relatable::class)) {
                    $rule[$i] = function () use ($r) {
                        $r->query = $r->query->newQuery();
                        return $r;
                    };
                }
            }

            return [$key => $rule];
        });
    }

    protected function getFilePath($file)
    {
        return storage_path("nova/laravel-nova-import-csv/tmp/{$file}");
    }

    private function responseError($error)
    {
        throw ValidationException::withMessages([
            0 => [$error],
        ]);
    }
}
