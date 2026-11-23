<?php

namespace App\Libraries;

use App\Models\OutcomeObjectiveModel;

/** Sinkronisasi relasi CP–TP; pasangan unik dijaga di aplikasi dan unique index. */
final class CurriculumLinks
{
    /** @param list<int|string> $objectiveIds */
    public static function syncForOutcome(int $outcomeId, array $objectiveIds): void
    {
        self::sync('learning_outcome_id', $outcomeId, 'learning_objective_id', $objectiveIds);
    }

    /** @param list<int|string> $outcomeIds */
    public static function syncForObjective(int $objectiveId, array $outcomeIds): void
    {
        self::sync('learning_objective_id', $objectiveId, 'learning_outcome_id', $outcomeIds);
    }

    public static function objectivesOf(int $outcomeId): array
    {
        return array_map('intval', array_column((new OutcomeObjectiveModel())->where('learning_outcome_id', $outcomeId)->findAll(), 'learning_objective_id'));
    }

    public static function outcomesOf(int $objectiveId): array
    {
        return array_map('intval', array_column((new OutcomeObjectiveModel())->where('learning_objective_id', $objectiveId)->findAll(), 'learning_outcome_id'));
    }

    private static function sync(string $ownCol, int $ownId, string $otherCol, array $ids): void
    {
        $want = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $have = array_map('intval', array_column((new OutcomeObjectiveModel())->where($ownCol, $ownId)->findAll(), $otherCol));

        $remove = array_diff($have, $want);
        if ($remove) {
            (new OutcomeObjectiveModel())->where($ownCol, $ownId)->whereIn($otherCol, $remove)->delete();
        }
        foreach (array_diff($want, $have) as $id) {
            (new OutcomeObjectiveModel())->insert([$ownCol => $ownId, $otherCol => $id]);
        }
    }
}
