<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Base Service Class
 * 
 * Provides common functionality for all service classes in the
 * Heavy Equipment Management system.
 */
abstract class BaseService
{
    /**
     * Execute a database transaction with automatic rollback on failure
     *
     * @param callable $callback
     * @param string $operation Operation description for logging
     * @return mixed
     * @throws \Throwable
     */
    protected function executeTransaction(callable $callback, string $operation = 'database operation'): mixed
    {
        return DB::transaction(function () use ($callback, $operation) {
            try {
                $result = $callback();
                
                Log::info("Successfully completed {$operation}", [
                    'user_id' => auth()->id(),
                    'timestamp' => now()
                ]);
                
                return $result;
            } catch (\Throwable $e) {
                Log::error("Failed to complete {$operation}: {$e->getMessage()}", [
                    'user_id' => auth()->id(),
                    'exception' => $e->getTraceAsString(),
                    'timestamp' => now()
                ]);
                
                throw $e;
            }
        });
    }

    /**
     * Log activity for audit trail
     *
     * @param string $action
     * @param mixed $model
     * @param array $changes
     * @return void
     */
    protected function logActivity(string $action, mixed $model = null, array $changes = []): void
    {
        try {
            $modelType = $model ? get_class($model) : null;
            $modelId = $model?->getKey();
            
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id(),
                'action' => $action,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'description' => $this->generateActivityDescription($action, $model),
                'changes' => !empty($changes) ? json_encode($changes) : null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now()
            ]);
        } catch (\Throwable $e) {
            // Log to system log if activity logging fails
            Log::warning('Failed to log activity', [
                'action' => $action,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
        }
    }

    /**
     * Generate human-readable activity description
     *
     * @param string $action
     * @param mixed $model
     * @return string
     */
    protected function generateActivityDescription(string $action, mixed $model = null): string
    {
        if (!$model) {
            return ucfirst(str_replace('_', ' ', $action));
        }

        $modelName = class_basename($model);
        $modelIdentifier = $this->getModelIdentifier($model);
        
        return match (true) {
            str_contains($action, 'created') => "Created {$modelName} {$modelIdentifier}",
            str_contains($action, 'updated') => "Updated {$modelName} {$modelIdentifier}",
            str_contains($action, 'deleted') => "Deleted {$modelName} {$modelIdentifier}",
            str_contains($action, 'restored') => "Restored {$modelName} {$modelIdentifier}",
            default => ucfirst(str_replace('_', ' ', $action)) . " {$modelName} {$modelIdentifier}"
        };
    }

    /**
     * Get model identifier for logging
     *
     * @param mixed $model
     * @return string
     */
    protected function getModelIdentifier(mixed $model): string
    {
        return match (get_class($model)) {
            'App\Models\Equipment' => "#{$model->asset_number}",
            'App\Models\User' => "#{$model->employee_id} ({$model->email})",
            'App\Models\WorkOrder' => "#{$model->work_order_number}",
            'App\Models\Inspection' => "#INS-{$model->id}",
            default => "#{$model->getKey()}"
        };
    }

    /**
     * Validate business rules before operation
     *
     * @param array $rules
     * @param mixed $model
     * @return void
     * @throws \App\Exceptions\HeavyEquipmentException
     */
    protected function validateBusinessRules(array $rules, mixed $model = null): void
    {
        foreach ($rules as $rule => $condition) {
            if (!$condition) {
                $this->handleBusinessRuleViolation($rule, $model);
            }
        }
    }

    /**
     * Handle business rule violations
     *
     * @param string $rule
     * @param mixed $model
     * @return void
     * @throws \App\Exceptions\HeavyEquipmentException
     */
    abstract protected function handleBusinessRuleViolation(string $rule, mixed $model = null): void;

    /**
     * Get cache key for service operations
     *
     * @param string $operation
     * @param array $parameters
     * @return string
     */
    protected function getCacheKey(string $operation, array $parameters = []): string
    {
        $class = strtolower(class_basename(static::class));
        $key = "{$class}:{$operation}";
        
        if (!empty($parameters)) {
            $key .= ':' . md5(serialize($parameters));
        }
        
        return $key;
    }

    /**
     * Clear cache tags related to this service
     *
     * @param array $tags
     * @return void
     */
    protected function clearCache(array $tags): void
    {
        foreach ($tags as $tag) {
            cache()->tags($tag)->flush();
        }
    }
}