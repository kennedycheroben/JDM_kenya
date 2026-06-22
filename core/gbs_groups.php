<?php

if (!function_exists('deleteGbsGroup')) {
    function deleteGbsGroup(PDO $pdo, int $gbsId): void
    {
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT leader_id FROM gbs_groups WHERE id = ? LIMIT 1');
            $stmt->execute([$gbsId]);
            $leaderId = (int)($stmt->fetchColumn() ?: 0);

            $pdo->prepare('DELETE FROM gbs_messages WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_resources WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_announcements WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_members WHERE gbs_id = ?')->execute([$gbsId]);
            $pdo->prepare('DELETE FROM gbs_groups WHERE id = ?')->execute([$gbsId]);

            if ($leaderId > 0) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM gbs_groups WHERE leader_id = ?');
                $stmt->execute([$leaderId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->prepare('UPDATE users SET is_gbs_leader = 0 WHERE id = ?')->execute([$leaderId]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
