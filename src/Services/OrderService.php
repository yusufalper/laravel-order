<?php

namespace Alper\LaravelOrder\Services;

use Illuminate\Database\Eloquent\Model;

class OrderService
{
    public string $className;
    public array $conditions;
    public string $orderAttrName;

    public function __construct(public Model $record)
    {
        $this->className = get_class($this->record);
        $this->conditions = $this->record->orderUnificationAttributes ?? [];
        $this->orderAttrName = $this->record->orderAttrName ?? 'order';
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
        return ((int) $this->className::whereNotNull($this->orderAttrName)->orderByDesc($this->orderAttrName)
                ->where(function ($q) use ($conditions, $record) {
                    foreach ($conditions as $condition) {
                        $q->where($condition, $record->{$condition});
                    }
                })->select(['id', $this->orderAttrName])->first()?->{$this->orderAttrName} ?? 0) + 1;
    }

    public function updateOrder(): int
    {
        $conditions = $this->conditions;
        $record = $this->record;
        $collection = $this->className::whereNotNull($this->orderAttrName)
            ->where(function ($q) use ($record, $conditions) {
                foreach ($conditions as $condition) {
                    $q->where($condition, $record->{$condition});
                }
            })->orderBy($this->orderAttrName)->get();

        $new_order = (int) $record->{$this->orderAttrName} ?? $this->newOrder();
        if (count($collection) > 0) {
            if ($collection->last()?->{$this->orderAttrName} < $record->{$this->orderAttrName}) {
                $new_order = $collection->last()->{$this->orderAttrName};
            } elseif ($collection->last()->{$this->orderAttrName} <= 0) {
                $new_order = 1;
            }
        } else {
            $new_order = 1;
        }

        if ($record->{$this->orderAttrName} == null) {
            $new_order = $this->newOrder();
        }

        while (true) {
            if (! ((int) $new_order - 1) > 0) {
                break;
            }
            $emptyOld = $collection->where($this->orderAttrName, ((int) $new_order - 1))->first();
            if ($emptyOld) {
                break;
            }
            $new_order = (int) $new_order - 1;
        }

        $old = $collection->where($this->orderAttrName, (int) $record->{$this->orderAttrName})->first();
        if ($old) {
            $changes = $collection->where($this->orderAttrName, '>=', (int) $record->{$this->orderAttrName});
            if ($new_order > $record->{$this->orderAttrName}) {
                $changes = $changes->where($this->orderAttrName, '<=', (int) $new_order);
            } else {
                $changes = $changes->where($this->orderAttrName, '>=', (int) $new_order);
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
                    if ($new_order >= $record->getOriginal($this->orderAttrName) && $c[$this->orderAttrName] <= $new_order) {
                        $c[$this->orderAttrName] = (int) $c[$this->orderAttrName] - 1;
                    } elseif ($c[$this->orderAttrName] >= $new_order) {
                        $c[$this->orderAttrName] = (int) $c[$this->orderAttrName] + 1;
                    }
                    $upsert[] = $c;
                }
                if (count($upsert) > 0) {
                    $this->className::query()->upsert($upsert, 'id', [$this->orderAttrName]);
                }
            }
        }
        return $new_order;
    }

    public function safeDeleteWithOrder(): void
    {
        $conditions = $this->conditions;
        $record = $this->record;
        if ($record->{$this->orderAttrName}) {
            $collection = $this->className::whereNotNull($this->orderAttrName)->orderBy($this->orderAttrName)
                ->where(function ($q) use ($record, $conditions) {
                    foreach ($conditions as $condition) {
                        $q->where($condition, $record->{$condition});
                    }
                })->get();

            $oldAfter = $collection->where($this->orderAttrName, '>', (int) $record->{$this->orderAttrName});
            if (count($oldAfter) > 0) {
                $upsert = [];
                foreach ($oldAfter as $oa) {
                    $oa = $oa->toArray();
                    foreach ($oa as $key => $attr) {
                        if (is_array($attr)) {
                            $oa[$key] = json_encode($attr);
                        }
                    }
                    $oa[$this->orderAttrName] = (int) $oa[$this->orderAttrName] - 1;
                    $upsert[] = $oa;
                }
                $this->className::query()->upsert($upsert, 'id', [$this->orderAttrName]);
            }
        }
        $record->delete();
    }

    public static function arrangeAllOrders(Model $model): void
    {
        $reSorts = get_class($model)::where(function ($q) use ($model) {
            foreach ($model->orderUnificationAttributes as $attribute) {
                $q->where($attribute, $model->{$attribute});
            }
        })->orderBy($model->orderAttrName)->get();
        $reSortsArr = [];
        $firstNumber = 1;
        foreach ($reSorts->whereNotNull($model->orderAttrName)->toArray() as $reSort) {
            foreach ($reSort as $key => $attr) {
                if (is_array($attr)) {
                    $reSort[$key] = json_encode($attr);
                }
            }
            $reSort['updated_at'] = now();
            $reSort[$model->orderAttrName] = $firstNumber;
            $reSortsArr[] = $reSort;
            $firstNumber += 1;
        }
        foreach ($reSorts->whereNull($model->orderAttrName)->toArray() as $reSort) {
            foreach ($reSort as $key => $attr) {
                if (is_array($attr)) {
                    $reSort[$key] = json_encode($attr);
                }
            }
            $reSort['updated_at'] = now();
            $reSort[$model->orderAttrName] = $firstNumber;
            $reSortsArr[] = $reSort;
            $firstNumber += 1;
        }
        get_class($model)::query()->upsert($reSortsArr, 'id', [$model->orderAttrName]);
    }
}
