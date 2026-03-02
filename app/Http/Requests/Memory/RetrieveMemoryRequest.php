<?php

declare(strict_types=1);

namespace App\Http\Requests\Memory;

use App\Models\MemoryCoreBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for hybrid memory retrieval.
 */
class RetrieveMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:1', 'max:1000'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'classification' => ['sometimes', 'array'],
            'classification.*' => ['string', Rule::in(MemoryCoreBlock::$validClassifications)],
            'semantic_weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'keyword_weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'graph_weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
        ];
    }

    /**
     * Get retrieval options from validated input.
     *
     * @return array<string, mixed>
     */
    public function getRetrievalOptions(): array
    {
        $options = [];

        if ($this->has('limit')) {
            $options['limit'] = $this->integer('limit');
        }

        if ($this->has('classification')) {
            $options['classification'] = $this->input('classification');
        }

        if ($this->has('semantic_weight')) {
            $options['semantic_weight'] = (float) $this->input('semantic_weight');
        }

        if ($this->has('keyword_weight')) {
            $options['keyword_weight'] = (float) $this->input('keyword_weight');
        }

        if ($this->has('graph_weight')) {
            $options['graph_weight'] = (float) $this->input('graph_weight');
        }

        return $options;
    }
}
