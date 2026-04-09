<?php

namespace App\Admin\Controllers;

use App\Models\Promotion;
use OpenAdmin\Admin\Controllers\AdminController;
use OpenAdmin\Admin\Form;
use OpenAdmin\Admin\Grid;
use OpenAdmin\Admin\Show;

class PromotionController extends AdminController
{
    protected $title = 'Promociones';

    protected function grid()
    {
        $grid = new Grid(new Promotion());
        $grid->model()->orderBy('priority')->orderByDesc('id');

        $grid->column('id', __('admin.id'))->sortable();
        $grid->column('code', 'Código');
        $grid->column('name', 'Nombre');
        $grid->column('type', 'Tipo')->label([
            Promotion::TYPE_PERCENTAGE => 'info',
            Promotion::TYPE_FIXED_AMOUNT => 'warning',
            Promotion::TYPE_BXGY => 'success',
        ]);
        $grid->column('is_active', 'Activa')->bool();
        $grid->column('is_automatic', 'Automática')->bool();
        $grid->column('is_stackable', 'Acumulable')->bool();
        $grid->column('priority', 'Prioridad')->sortable();
        $grid->column('starts_at', 'Inicio');
        $grid->column('ends_at', 'Fin');

        $grid->filter(function ($filter) {
            $filter->like('name', 'Nombre');
            $filter->like('code', 'Código');
            $filter->equal('type', 'Tipo')->select([
                Promotion::TYPE_PERCENTAGE => 'Porcentaje',
                Promotion::TYPE_FIXED_AMOUNT => 'Monto fijo',
                Promotion::TYPE_BXGY => 'BxGy (2x1, 3x2, etc)',
            ]);
            $filter->equal('is_active', 'Activa')->radio([
                '' => 'Todos',
                1 => 'Sí',
                0 => 'No',
            ]);
        });

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(Promotion::findOrFail($id));

        $show->field('id', __('admin.id'));
        $show->field('code', 'Código');
        $show->field('name', 'Nombre');
        $show->field('type', 'Tipo');
        $show->field('description', 'Descripción');
        $show->field('is_active', 'Activa')->bool();
        $show->field('is_automatic', 'Automática')->bool();
        $show->field('is_stackable', 'Acumulable')->bool();
        $show->field('priority', 'Prioridad');
        $show->field('starts_at', 'Inicio');
        $show->field('ends_at', 'Fin');
        $show->field('usage_limit', 'Límite de uso');
        $show->field('usage_count', 'Usos acumulados');
        $show->field('settings', 'Configuración')->json();
        $show->field('created_at', __('admin.created_at'));
        $show->field('updated_at', __('admin.updated_at'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new Promotion());

        $form->text('code', 'Código')
            ->help('Opcional. Si se completa, puede aplicarse desde checkout con promotion_code.')
            ->rules('nullable|max:80|unique:promotions,code,{{id}}');
        $form->text('name', 'Nombre')->rules('required|max:255');
        $form->select('type', 'Tipo')
            ->options([
                Promotion::TYPE_PERCENTAGE => 'Porcentaje',
                Promotion::TYPE_FIXED_AMOUNT => 'Monto fijo',
                Promotion::TYPE_BXGY => 'BxGy (2x1, 3x2, etc)',
            ])->rules('required');
        $form->textarea('description', 'Descripción');

        $form->switch('is_active', 'Activa')->default(1);
        $form->switch('is_automatic', 'Automática')->default(0)
            ->help('Si está activa, se evalúa en checkout sin necesidad de código.');
        $form->switch('is_stackable', 'Acumulable')->default(0)
            ->help('Si está inactiva, al aplicarse esta promo se detiene la evaluación de las demás.');
        $form->number('priority', 'Prioridad')->default(100)->rules('required|integer');

        $form->datetime('starts_at', 'Inicio');
        $form->datetime('ends_at', 'Fin');
        $form->number('usage_limit', 'Límite de uso')->help('Opcional');
        $form->display('usage_count', 'Usos acumulados')->default(0);

        $form->decimal('settings_amount', 'Monto fijo')
            ->default(function (Form $form) {
                return $this->defaultSettingNumber($form, 'amount');
            })
            ->rules('nullable|numeric|min:0')
            ->help('Para promociones tipo fixed_amount. Se guarda en settings.amount');

        $form->decimal('settings_percentage', 'Porcentaje')
            ->default(function (Form $form) {
                return $this->defaultSettingNumber($form, 'percentage');
            })
            ->rules('nullable|numeric|min:0|max:100')
            ->help('Para promociones tipo percentage. Se guarda en settings.percentage');

        $form->textarea('settings', 'Configuración (JSON)')
            ->help(
                "Ejemplos:\n" .
                "percentage: {\"percentage\":10,\"target_item_type\":\"ticket_seat\"}\n" .
                "fixed_amount: {\"amount\":500,\"target_item_type\":\"ticket_seat\"}\n" .
                "bxgy (2x1): {\"buy_qty\":2,\"pay_qty\":1,\"target_item_type\":\"ticket_seat\"}\n" .
                "bxgy productos por código: {\"buy_qty\":2,\"pay_qty\":1,\"target_item_type\":\"product\",\"target_codes\":[\"COMBO_2G_PG\"]}\n" .
                "condiciones: {\"buy_qty\":2,\"pay_qty\":1,\"target_item_type\":\"product\",\"target_codes\":[\"COMBO_2G_PG\"],\"conditions\":{\"aggregator\":\"all\",\"conditions\":[{\"type\":\"cart_quantity\",\"item_type\":\"ticket_seat\",\"operator\":\">=\",\"value\":2}]}}"
            )
            ->rules('required|json')
            ->default('{}');

        $form->divider('Constructor de condiciones');
        $form->html('
            <style>
                #has-many-conditions_groups,
                #has-many-conditions_builder {
                    overflow-x: auto;
                }
                #has-many-conditions_groups table,
                #has-many-conditions_builder table {
                    min-width: 1100px;
                }
                #has-many-conditions_groups td,
                #has-many-conditions_builder td {
                    vertical-align: top;
                }
            </style>
        ')->plain();

        $form->select('conditions_aggregator', 'Cómo combinar condiciones')
            ->options([
                'all' => 'Todas (AND)',
                'any' => 'Cualquiera (OR)',
            ])
            ->default(function (Form $form) {
                return $this->defaultConditionAggregator($form);
            })
            ->help('Estas condiciones se guardan dentro de settings.conditions.');

        $form->virtualTable('conditions_groups', 'Grupos de condiciones (anidados)', function ($table) {
            $table->text('group_id', 'Group ID')
                ->help('ID único del grupo. Ej: G1, G2');
            $table->text('parent_group_id', 'Parent Group ID')
                ->help('Vacío = cuelga del grupo raíz');
            $table->select('aggregator', 'Aggregator')->options([
                'all' => 'all (AND)',
                'any' => 'any (OR)',
            ])->default('all');
            $table->text('label', 'Label')
                ->help('Opcional, solo descriptivo');
        })->default(function (Form $form) {
            return $this->defaultConditionGroups($form);
        });

        $form->virtualTable('conditions_builder', 'Condiciones (iterativo)', function ($table) {
            $table->text('group_id', 'Group ID')
                ->help('Vacío = grupo raíz');

            $table->select('type', 'Tipo')->options([
                'cart_quantity' => 'Cantidad en carrito',
                'cart_subtotal' => 'Subtotal en carrito',
                'context_value' => 'Valor de contexto',
            ])->default('cart_quantity');

            $table->select('item_type', 'Item type')->options([
                'ticket_seat' => 'ticket_seat',
                'product' => 'product',
                'combo' => 'combo',
            ]);

            $table->text('item_codes', 'Item codes')
                ->help('Opcional, separados por coma. Ej: COMBO_2G_PG,COMBO_XL');

            $table->text('context_key', 'Context key')
                ->help('Solo para type=context_value. Ej: screening_id');

            $table->select('operator', 'Operador')->options([
                '>=' => '>=',
                '>' => '>',
                '<=' => '<=',
                '<' => '<',
                '=' => '=',
                '!=' => '!=',
                'in' => 'in',
                'not_in' => 'not_in',
            ])->default('>=');

            $table->text('expected_value', 'Valor')
                ->help('Para in/not_in usar JSON array, ej: [1,2,3]');
        })->default(function (Form $form) {
            return $this->defaultConditionRows($form);
        });

        $form->ignore([
            'settings_amount',
            'settings_percentage',
            'conditions_aggregator',
            'conditions_groups',
            'conditions_builder',
        ]);

        $form->saving(function (Form $form) {
            $settings = $this->decodeSettings($form->settings);

            $amountInput = request()->input('settings_amount');
            if ($amountInput === null || $amountInput === '') {
                unset($settings['amount']);
            } elseif (is_numeric($amountInput)) {
                $settings['amount'] = round((float) $amountInput, 2);
            }

            $percentageInput = request()->input('settings_percentage');
            if ($percentageInput === null || $percentageInput === '') {
                unset($settings['percentage']);
            } elseif (is_numeric($percentageInput)) {
                $settings['percentage'] = round((float) $percentageInput, 2);
            }

            $aggregator = strtolower(trim((string) request()->input('conditions_aggregator', 'all')));
            if (!in_array($aggregator, ['all', 'any'], true)) {
                $aggregator = 'all';
            }

            $rows = request()->input('conditions_builder', []);
            $conditions = $this->normalizeConditionRows(is_array($rows) ? $rows : []);
            $groups = request()->input('conditions_groups', []);
            $groups = $this->normalizeConditionGroups(is_array($groups) ? $groups : []);

            $tree = $this->buildConditionTree($aggregator, $groups, $conditions);

            if ($tree !== null) {
                $settings['conditions'] = $tree;
            } else {
                unset($settings['conditions']);
            }

            $form->settings = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        });

        return $form;
    }

    private function defaultConditionAggregator(Form $form): string
    {
        $settings = $this->decodeSettings($form->model()->settings ?? []);
        $aggregator = strtolower((string) (($settings['conditions']['aggregator'] ?? 'all')));

        return in_array($aggregator, ['all', 'any'], true) ? $aggregator : 'all';
    }

    private function defaultSettingNumber(Form $form, string $key): ?float
    {
        $settings = $this->decodeSettings($form->model()->settings ?? []);
        $value = $settings[$key] ?? null;

        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function defaultConditionGroups(Form $form): array
    {
        $settings = $this->decodeSettings($form->model()->settings ?? []);
        $root = $settings['conditions'] ?? null;
        if (!is_array($root)) {
            return [];
        }

        $acc = [];
        $index = 1;
        $this->flattenConditionTree($root, '', $acc, $index);

        return $acc['groups'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function defaultConditionRows(Form $form): array
    {
        $settings = $this->decodeSettings($form->model()->settings ?? []);
        $root = $settings['conditions'] ?? null;
        if (!is_array($root)) {
            return [];
        }

        $acc = [];
        $index = 1;
        $this->flattenConditionTree($root, '', $acc, $index);

        return $acc['conditions'];
    }

    /**
     * @param mixed $raw
     * @return array<string,mixed>
     */
    private function decodeSettings($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeConditionRows(array $rows): array
    {
        $conditions = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtolower(trim((string) ($row['type'] ?? '')));
            if ($type === '') {
                continue;
            }

            $operator = strtolower(trim((string) ($row['operator'] ?? '>=')));
            if ($operator === '') {
                $operator = '>=';
            }

            $rawValue = $row['expected_value'] ?? ($row['value'] ?? null);
            $value = $this->normalizeConditionValue($rawValue, $operator);

            $condition = [
                'group_id' => strtoupper(trim((string) ($row['group_id'] ?? ''))),
                'type' => $type,
                'operator' => $operator,
                'value' => $value,
            ];

            if (in_array($type, ['cart_quantity', 'cart_subtotal'], true)) {
                $itemType = trim((string) ($row['item_type'] ?? ''));
                if ($itemType !== '') {
                    $condition['item_type'] = $itemType;
                }

                $codes = $this->parseCodes((string) ($row['item_codes'] ?? ''));
                if (!empty($codes)) {
                    $condition['item_codes'] = $codes;
                }
            }

            if ($type === 'context_value') {
                $contextKey = trim((string) ($row['context_key'] ?? ''));
                if ($contextKey !== '') {
                    $condition['context_key'] = $contextKey;
                }
            }

            $conditions[] = $condition;
        }

        return $conditions;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function normalizeConditionGroups(array $rows): array
    {
        $groups = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $groupId = strtoupper(trim((string) ($row['group_id'] ?? '')));
            if ($groupId === '' || isset($seen[$groupId])) {
                continue;
            }

            $parentGroupId = strtoupper(trim((string) ($row['parent_group_id'] ?? '')));
            $aggregator = strtolower(trim((string) ($row['aggregator'] ?? 'all')));
            if (!in_array($aggregator, ['all', 'any'], true)) {
                $aggregator = 'all';
            }

            $groups[] = [
                'group_id' => $groupId,
                'parent_group_id' => $parentGroupId,
                'aggregator' => $aggregator,
                'label' => trim((string) ($row['label'] ?? '')),
            ];
            $seen[$groupId] = true;
        }

        return $groups;
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     * @param array<int,array<string,mixed>> $conditions
     * @return array<string,mixed>|null
     */
    private function buildConditionTree(string $rootAggregator, array $groups, array $conditions): ?array
    {
        $root = [
            'aggregator' => $rootAggregator,
            'conditions' => [],
        ];

        $nodesByGroupId = [];
        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            $nodesByGroupId[$groupId] = [
                'aggregator' => $group['aggregator'],
                'conditions' => [],
            ];
        }

        foreach ($groups as $group) {
            $groupId = $group['group_id'];
            $parentId = $group['parent_group_id'];

            $parentNode = &$root;
            if ($parentId !== '' && isset($nodesByGroupId[$parentId]) && $parentId !== $groupId) {
                $parentNode = &$nodesByGroupId[$parentId];
            }

            $parentNode['conditions'][] = &$nodesByGroupId[$groupId];
            unset($parentNode);
        }

        foreach ($conditions as $condition) {
            $groupId = $condition['group_id'] ?? '';
            unset($condition['group_id']);

            if ($groupId !== '' && isset($nodesByGroupId[$groupId])) {
                $nodesByGroupId[$groupId]['conditions'][] = $condition;
                continue;
            }

            $root['conditions'][] = $condition;
        }

        $root = $this->pruneConditionTree($root);
        if (empty($root['conditions'])) {
            return null;
        }

        return $root;
    }

    /**
     * @param array<string,mixed> $node
     * @return array<string,mixed>
     */
    private function pruneConditionTree(array $node): array
    {
        $children = $node['conditions'] ?? [];
        if (!is_array($children)) {
            $node['conditions'] = [];
            return $node;
        }

        $kept = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            if (isset($child['conditions']) && is_array($child['conditions'])) {
                $child = $this->pruneConditionTree($child);
                if (!empty($child['conditions'])) {
                    $kept[] = $child;
                }
                continue;
            }

            $kept[] = $child;
        }

        $node['conditions'] = $kept;

        return $node;
    }

    /**
     * @param array<string,mixed> $node
     * @param string $currentGroupId
     * @param array<string,mixed> $acc
     */
    private function flattenConditionTree(array $node, string $currentGroupId, array &$acc, int &$index): void
    {
        if (!isset($acc['groups']) || !is_array($acc['groups'])) {
            $acc['groups'] = [];
        }
        if (!isset($acc['conditions']) || !is_array($acc['conditions'])) {
            $acc['conditions'] = [];
        }

        $children = $node['conditions'] ?? [];
        if (!is_array($children)) {
            return;
        }

        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }

            if (isset($child['conditions']) && is_array($child['conditions'])) {
                $groupId = 'G' . $index++;
                $acc['groups'][] = [
                    'group_id' => $groupId,
                    'parent_group_id' => $currentGroupId,
                    'aggregator' => (string) ($child['aggregator'] ?? 'all'),
                    'label' => '',
                ];

                $this->flattenConditionTree($child, $groupId, $acc, $index);
                continue;
            }

            $itemCodes = [];
            if (!empty($child['item_codes']) && is_array($child['item_codes'])) {
                $itemCodes = $child['item_codes'];
            } elseif (!empty($child['target_codes']) && is_array($child['target_codes'])) {
                $itemCodes = $child['target_codes'];
            }

            $value = $child['value'] ?? null;
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $acc['conditions'][] = [
                'group_id' => $currentGroupId,
                'type' => (string) ($child['type'] ?? ''),
                'item_type' => (string) ($child['item_type'] ?? ''),
                'item_codes' => implode(',', array_map('strval', $itemCodes)),
                'context_key' => (string) ($child['context_key'] ?? ''),
                'operator' => (string) ($child['operator'] ?? '>='),
                'expected_value' => $value !== null ? (string) $value : '',
            ];
        }
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function normalizeConditionValue($value, string $operator)
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }

            if (in_array($operator, ['in', 'not_in'], true)) {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                return array_values(array_filter(array_map('trim', explode(',', $trimmed)), fn ($v) => $v !== ''));
            }

            if (is_numeric($trimmed)) {
                return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
            }

            return $trimmed;
        }

        return $value;
    }

    /**
     * @return array<int,string>
     */
    private function parseCodes(string $value): array
    {
        $parts = array_map(static fn (string $code) => strtoupper(trim($code)), explode(',', $value));
        $parts = array_values(array_filter($parts, static fn (string $code) => $code !== ''));

        return array_values(array_unique($parts));
    }
}
