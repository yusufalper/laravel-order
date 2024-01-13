<?php

namespace Alper\LaravelOrder\Services;

use Illuminate\Database\Eloquent\Model;

class OrderService
{
    public string $className;
    public array $conditions;
    public function __construct(public Model $record)
    {
        $this->className = get_class($this->record);
        $this->conditions = $this->record->orderUnificationAttributes ?? [];
    }

    public function saveNew(int|null $userGivenOrder = null): int
    {
        $newOrder = $this->newOrder();
        if ($userGivenOrder !== null && $userGivenOrder !== $newOrder) {
            $newOrder = $this->updateOrder();
        }

        return $newOrder;
    }

    public function newOrder(): int
    {
        $conditions = $this->conditions;
        $record = $this->record;
        return ((int)$this->className::whereNotNull('order')->orderByDesc('order')
                ->where(function ($q) use ($conditions, $record) {
                    foreach ($conditions as $condition) {
                        $q->where($condition, $record->{$condition});
                    }
                })->select(['id', 'order'])->first()?->order ?? 0) + 1;
    }

    public function updateOrder(): int
    {
        $conditions = $this->conditions;
        $record = $this->record;
        $collection = $this->className::whereNotNull('order')
            ->where(function ($q) use ($record, $conditions) {
                foreach ($conditions as $condition) {
                    $q->where($condition, $record->{$condition});
                }
            })->orderBy('order')->get();

        $new_order = (int)$record->order ?? $this->newOrder();
        if (count($collection) > 0) {
            if ($collection->last()?->order < $record->order) {
                $new_order = $collection->last()->order;
            } elseif ($collection->last()->order <= 0) {
                $new_order = 1;
            }
        } else {
            $new_order = 1;
        }

        if ($record->order == null) {
            $new_order = $this->newOrder();
        }

        while (true) {
            if (! ((int)$new_order - 1) > 0) {
                break;
            }
            $emptyOld = $collection->where('order', ((int)$new_order - 1))->first();
            if ($emptyOld) {
                break;
            }
            $new_order = (int)$new_order - 1;
        }

        $old = $collection->where('order', $record->order)->first();
        if ($old) {
            $changes = $collection->where('order', '>=', $record->order);
            if ($new_order > $record->order) {
                $changes = $changes->where('order', '<=', $new_order);
            } else {
                $changes = $changes->where('order', '>=', $new_order);
            }
            if (count($changes) > 0) {
                $upsert = [];
                foreach ($changes as $c) {
                    $c = $c->toArray();
                    foreach ($c as $key => $attr) {
                        if (is_array($attr)) {
                            $c[$key] = json_encode($attr);
                        }
                    }
                    if ($new_order > $record->order) {
                        $c['order'] = (int) $c['order'] - 1;
                    } else {
                        $c['order'] = (int) $c['order'] + 1;
                    }
                    $upsert[] = $c;
                }
                if (count($upsert) > 0) {
                    $this->className::query()->upsert($upsert, 'id', ['order']);
                }
            }
        }
        return $new_order;
    }

    public function safeDeleteWithOrder(): void
    {
        $conditions = $this->conditions;
        $record = $this->record;
        if ($record->order) {
            $collection = $this->className::whereNotNull('order')->orderBy('order')
                ->where(function ($q) use ($record, $conditions) {
                    foreach ($conditions as $condition) {
                        $q->where($condition, $record->{$condition});
                    }
                })->get();

            $oldAfter = $collection->where('order', '>', $record->order);
            if (count($oldAfter) > 0) {
                $upsert = [];
                foreach ($oldAfter as $oa) {
                    $oa = $oa->toArray();
                    foreach ($oa as $key => $attr) {
                        if (is_array($attr)) {
                            $oa[$key] = json_encode($attr);
                        }
                    }
                    $oa['order'] = (int) $oa['order'] - 1;
                    $upsert[] = $oa;
                }
                $this->className::query()->upsert($upsert, 'id', ['order']);
            }
        }
        $record->delete();
    }
}
