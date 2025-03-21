<?php

namespace App\Forms\Elements;

use Phalcon\Forms\Element\AbstractElement;

/*
 * A correct way to add checkbox and radio groups
 *
 * $this->add(new CheckGroup('group_element_name', [
 *     'type' => 'radio', // checkbox / radio
 *     'data' => [
 *         'option1' => [
 *             'label' => 'label_option1',
 *             'value' => 'value_option1',
 *              'checked' => true,
 *         ],
 *         'option2' => [
 *             'label' => 'label_option2',
 *             'value' => 'value_option2',
 *             'checked' => false,
 *         ]
 *     ],
 *     'class' => [
 *         'container' => 'some_container_class',
 *         'input' => 'some_input_class',
 *         'label' => 'some_label_class',
 *     ],
 *     'name' => 'checkbox_name[]', // required for checkbox with multiple selections
 *     'required' => '',
 * ]));
 *
 *
 * the css classes can also be added from volt / view template as attribute
 *
 *  <div class="col-span-6 relative z-0 flex flex-col md:flex-row justify-start md:items-center space-y-2 md:space-y-0 md:space-x-2">
 *      <div class="flex flex-col">
 *          {{ Label('group_element_name', __('label_group')) }}
 *          {{ form.render('group_element_name', ['class': {'container': 'flex flex-row justify-start space-x-4', 'input': 'w-4 h-4 text-gray-900 bg-gray-100 border-gray-300 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600', 'label': 'inline ml-2 text-sm font-medium text-gray-900 dark:text-white'}]) }}
 *      </div>
 *  </div>
*/
class CheckGroup extends AbstractElement
{
    protected array $data = [];
    protected string $type = 'checkbox';
    protected string $containerClass = '';
    protected string $labelClass = '';
    protected string $inputClass = '';

    public function render(array $attributes = []): string
    {
        if (!empty($attributes)) {
            foreach ($attributes as $key => $val) {
                $this->setAttribute($key, $val);
            }
        }

        $attributes = $this->getAttributes();
        $this->resetAttributes($attributes);

        $required = '';
        if (isset($attributes['required'])) {
            $required = ' required';
        }

        $elementName = !isset($attributes['name']) ? $this->getName() : $attributes['name'];
        $elementNameIdPrefix = rtrim($elementName, '[]');
        $rendered = '';
        foreach ($this->data as $key => $element) {
            $rendered .= '<div'.$this->containerClass.'>';

            $key = strtolower($elementNameIdPrefix.'_'.$key);
            $value = $element['value'] ?? 0;
            $label = $element['label'] ?? '';
            $checked = isset($element['checked']) && $element['checked'] ? ' checked' : '';

            $rendered = rtrim($rendered); // clear the last empty line
            $rendered .= <<<ELEMENT

    <div class="inline relative z-0">
        <input type="{$this->type}" id="{$key}" name="{$elementName}" value="$value"{$this->inputClass}$required$checked>
        <label for="{$key}"{$this->labelClass}>{$label}</label>
    </div>

ELEMENT;
            $rendered .= '</div>';
        }

        return $rendered;
    }

    private function resetAttributes($attributes): void
    {
        if (isset($attributes['type'])) {
            $this->type = match ($attributes['type']) {
                'checkbox' => 'checkbox',
                default    => 'radio',
            };
        }
        if (isset($attributes['data'])) {
            $this->data = $attributes['data'];
        }
        if (isset($attributes['class'])) {
            if (is_array($attributes['class'])) {
                $this->containerClass = $attributes['class']['container'] ?? $this->containerClass;
                if ('' !== $this->containerClass) {
                    $this->containerClass = ' class="'.$this->containerClass.'"';
                }
                $this->inputClass .= $attributes['class']['input'] ?? $this->inputClass;
                if ('' !== $this->inputClass) {
                    $this->inputClass = ' class="'.$this->inputClass.'"';
                }
                $this->labelClass = $attributes['class']['label'] ?? $this->labelClass;
                if ('' !== $this->labelClass) {
                    $this->labelClass = ' class="'.$this->labelClass.'"';
                }
            }
            if (is_string($attributes['class'])) {
                $this->inputClass = $attributes['class'];
            }
        }
    }
}
