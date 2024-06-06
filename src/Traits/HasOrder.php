<?php

namespace Alper\LaravelOrder\Traits;

use Alper\LaravelOrder\Services\OrderService;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;


/*
 * Requires integer and nullable attribute from Model Migration $orderAttrName in Model
 * Gets optional $orderUnificationAttributes property from Model Class.
 * Gets optional orderAttrName property from Model Class default is 'order'.
 */
trait HasOrder
{
    #important: public string $orderAttrName = 'order';
    #important: public array $orderUnificationAttributes = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(/**
         * @throws Throwable
         */ function ($model) {
            DB::beginTransaction();
            try {
                $model->{$model->orderAttrName} = (new OrderService($model))->saveNew($model->{$model->orderAttrName});
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updating(/**
         * @throws Throwable
         */ function ($model) {
            if ($model->{$model->orderAttrName} !== $model->getOriginal($model->orderAttrName)) {
                DB::beginTransaction();
                try {
                    $model->{$model->orderAttrName} = (new OrderService($model))->updateOrder();
                } catch (Exception|Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
                DB::commit();
            }
        });
        static::deleting(/**
         * @throws Throwable
         */ function ($model) {
            DB::beginTransaction();
            try {
                (new OrderService($model))->safeDeleteWithOrder();
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
        static::updated(/**
         * @throws Throwable
         */ function ($model) {
            DB::beginTransaction();
            try {
                if ($model->{$model->orderAttrName} !== $model->getOriginal($model->orderAttrName)) {
                    $reSorts = get_class($model)::where(function ($q) use ($model) {
                        foreach ($model->orderUnificationAttributes as $attribute) {
                            $q->where($attribute, $model->{$attribute});
                        }
                    })->whereNotNull($model->orderAttrName)
                        ->orderBy($model->orderAttrName)->get();
                    $firstNumber = (int) $reSorts->first()->{$model->orderAttrName};
                    if ($firstNumber === 0) {
                        $firstNumber = 1;
                    }
                    $reSortsArr = [];
                    foreach ($reSorts->toArray() as $reSort) {
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
                    OrderService::arrangeAllOrders($model);
                }
            } catch (Exception|Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            DB::commit();
        });
    }
}
