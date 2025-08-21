<?php

namespace App\Forms\Components;

/**
 * 📄 TEXTAREA FIELD
 */
class Textarea extends FormField
{
    protected int $rows = 4;

    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function render(): string
    {
        return view('forms.components.textarea', [
            'field' => $this,
            'rows' => $this->rows,
        ])->render();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'rows' => $this->rows,
        ]);
    }
}
