<?php

namespace App\Http\Requests\Admin;

use App\Models\Bot\BotDataSource;
use App\Models\Bot\BotFlow;
use App\Support\BotNodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The graph as the editor saves it.
 *
 * Validated hard, because this is a form that writes the behaviour of a public
 * service. Node types, actions and data sources are checked against the
 * vocabularies the engine implements — a node type nothing can execute would
 * be a dead end in somebody's conversation, and a free-text action name would
 * turn the editor into a way to run arbitrary code.
 */
class BotFlowGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('flow') ?? new BotFlow());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Every declared setting gets a rule, because a form request returns
        // only the keys it validated — an undeclared one would be dropped on
        // save and the editor would quietly eat its own configuration.
        $config = [];

        foreach (BotNodes::configRules() as $key => $rules) {
            $config["nodes.*.config.$key"] = $rules;
        }

        $config['nodes.*.config.input'] = ['nullable', 'string', Rule::in(array_keys(BotNodes::inputKinds()))];
        $config['nodes.*.config.action'] = ['nullable', 'string', Rule::in(array_keys(BotNodes::actions()))];
        $config['nodes.*.config.on_invalid'] = ['nullable', 'string', Rule::in(array_keys(BotNodes::retryBehaviours()))];

        return $config + [
            'nodes' => ['required', 'array', 'min:1', 'max:200'],
            'nodes.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'nodes.*.type' => ['required', 'string', Rule::in(array_keys(BotNodes::types()))],
            'nodes.*.label' => ['nullable', 'string', 'max:120'],
            'nodes.*.position.x' => ['required', 'integer', 'between:-20000,20000'],
            'nodes.*.position.y' => ['required', 'integer', 'between:-20000,20000'],
            'nodes.*.config' => ['nullable', 'array'],
            'nodes.*.config.options.*.value' => ['required_with:nodes.*.config.options', 'string', 'max:32'],
            'nodes.*.config.options.*.label' => ['required_with:nodes.*.config.options', 'string', 'max:120'],

            'edges' => ['present', 'array', 'max:400'],
            'edges.*.from' => ['required', 'string', 'max:64'],
            'edges.*.to' => ['required', 'string', 'max:64'],
            'edges.*.condition' => ['nullable', 'string', 'max:64'],
            'edges.*.label' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $nodes = collect($this->input('nodes', []));
            $keys = $nodes->pluck('key');

            if ($keys->count() !== $keys->unique()->count()) {
                $validator->errors()->add('nodes', 'Ada kunci node yang sama lebih dari sekali.');
            }

            $starts = $nodes->where('type', BotNodes::START)->count();

            if ($starts !== 1) {
                // Two starts would make which one runs a matter of insert order.
                $validator->errors()->add('nodes', $starts === 0
                    ? 'Alur harus punya tepat satu node Mulai.'
                    : 'Alur hanya boleh punya satu node Mulai, saat ini ada '.$starts.'.');
            }

            foreach ($this->input('edges', []) as $index => $edge) {
                foreach (['from', 'to'] as $end) {
                    if (! $keys->contains($edge[$end] ?? null)) {
                        $validator->errors()->add("edges.$index.$end", 'Sambungan menunjuk node yang tidak ada.');
                    }
                }
            }

            $sources = BotDataSource::pluck('slug');

            foreach ($nodes as $index => $node) {
                if (($node['type'] ?? null) === BotNodes::DATA_SOURCE) {
                    $slug = data_get($node, 'config.data_source');

                    if (! $sources->contains($slug)) {
                        $validator->errors()->add("nodes.$index.config.data_source", 'Sumber data tidak dikenali.');
                    }
                }
            }
        });
    }
}
