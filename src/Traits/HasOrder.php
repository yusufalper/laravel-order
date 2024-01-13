<?php

namespace Alper\LaravelOrder\Traits;

use Alper\LaravelOrder\Services\OrderService;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

trait HasOrder
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            DB::beginTransaction();
            try {
                $model->order = (new OrderService($model))->saveNew($model->order);
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updating(function ($model) {
            if ($model->order !== $model->getOriginal('order')) {
                DB::beginTransaction();
                try {
                    $model->order = (new OrderService($model))->updateOrder();
                } catch (Exception|Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
                DB::commit();
            }
        });
        static::deleting(function ($model) {
            DB::beginTransaction();
            try {
                (new OrderService($model))->safeDeleteWithOrder();
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updated(function ($model) {
            DB::beginTransaction();
            try {
                if ($model->order !== $model->getOriginal('order')) {
                    $reSorts = get_class($model)::where(function ($q) use ($model){
                        foreach ($model->orderUnificationAttributes as $attribute) {
                            $q->where($attribute, $model->{$attribute});
                        }
                    })->whereNotNull('order')
                        ->orderBy('order')->get();
                    $firstNumber = (int)$reSorts->first()->order;
                    $reSortsArr = [];
                    foreach ($reSorts->toArray() as $reSort) {
                        foreach ($reSort as $key => $attr) {
                            if (is_array($attr)) {
                                $reSort[$key] = json_encode($attr);
                            }
                        }
                        $reSort['updated_at'] = now();
                        $reSort['order'] = $firstNumber;
                        $reSortsArr[] = $reSort;
                        $firstNumber += 1;
                    }
                    get_class($model)::query()->upsert($reSortsArr, 'id', ['order']);
                }
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
    }
}
