<?php
/**
 * thread Model for handling database operations
 */

class Thread
{
    private ?PDO $db;

    public function __construct()
    {
        $this->db = getDatabaseConnection();
    }

    /**
     * Get or create active thread thread
     */
    public function getActiveThread(string $userId): ?array
    {
        if (!$this->db) return null;
        
        try {
            // Find active thread (last 30 minutes)
            $stmt = $this->db->prepare("
                SELECT id, thread 
                FROM conversas 
                WHERE usuario_id = ? 
                AND timestamp_inicio > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                AND timestamp_fim IS NULL
                ORDER BY timestamp_inicio DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $conversa = $stmt->fetch();
            
            if ($conversa) {
                return [
                    'id' => $conversa['id'],
                    'thread' => json_decode($conversa['thread'], true) ?: []
                ];
            }
            
            // Create new thread if none found
            $stmt = $this->db->prepare("INSERT INTO conversas (usuario_id, thread) VALUES (?, ?)");
            $stmt->execute([$userId, json_encode([])]);
            
            return [
                'id' => $this->db->lastInsertId(),
                'thread' => []
            ];
            
        } catch (PDOException $e) {
            error_log("Failed to get thread: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add user message to thread using OpenAI format
     */
    public function addUserMessage(array &$thread, string $message): void
    {
        $thread[] = [
            'role' => 'user',
            'content' => $message
        ];
    }

    /**
     * Add assistant message to thread using OpenAI format
     */
    public function addAssistantMessage(array &$thread, string $message): void
    {
        $thread[] = [
            'role' => 'assistant',
            'content' => $message
        ];
    }

    /**
     * Update thread thread
     */
    public function updateThread(int $threadId, array $thread): bool
    {
        if (!$this->db) return false;
        
        try {
            $stmt = $this->db->prepare("UPDATE conversas SET thread = ? WHERE id = ?");
            return $stmt->execute([json_encode($thread), $threadId]);
        } catch (PDOException $e) {
            error_log("Failed to update thread: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Close thread (end session)
     */
    public function closethread(int $threadId): bool
    {
        if (!$this->db) return false;
        
        try {
            $stmt = $this->db->prepare("UPDATE conversas SET timestamp_fim = NOW() WHERE id = ?");
            return $stmt->execute([$threadId]);
        } catch (PDOException $e) {
            error_log("Failed to close thread: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notes from database
     */
    public function getNotes(): array
    {
        if (!$this->db) return [];
        
        try {
            $stmt = $this->db->query("SELECT * FROM notes ORDER BY created_at DESC");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Failed to fetch notes: " . $e->getMessage());
            return [];
        }
    }
}