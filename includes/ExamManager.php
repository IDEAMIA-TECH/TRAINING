<?php
class ExamManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createExam($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO exams (course_id, title, description, duration, passing_score, attempts_allowed)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['course_id'],
            $data['title'],
            $data['description'],
            $data['duration'],
            $data['passing_score'],
            $data['attempts_allowed']
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    public function addQuestion($exam_id, $data) {
        $stmt = $this->conn->prepare("
            INSERT INTO exam_questions (exam_id, question, type, points, order_index)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $exam_id,
            $data['question'],
            $data['type'],
            $data['points'],
            $data['order_index']
        ]);
        
        $question_id = $this->conn->lastInsertId();
        
        if ($data['type'] === 'multiple_choice') {
            foreach ($data['options'] as $index => $option) {
                $stmt = $this->conn->prepare("
                    INSERT INTO question_options (question_id, option_text, is_correct, order_index)
                    VALUES (?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $question_id,
                    $option['text'],
                    $option['is_correct'],
                    $index
                ]);
            }
        }
        
        return $question_id;
    }
    
    public function startExam($exam_id, $user_id) {
        // Verificar intentos previos
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as attempts, e.attempts_allowed
            FROM exam_attempts ea
            JOIN exams e ON e.id = ea.exam_id
            WHERE ea.exam_id = ? AND ea.user_id = ?
        ");
        $stmt->execute([$exam_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['attempts'] >= $result['attempts_allowed']) {
            throw new Exception("Has alcanzado el número máximo de intentos permitidos");
        }
        
        // Crear nuevo intento
        $stmt = $this->conn->prepare("
            INSERT INTO exam_attempts (exam_id, user_id, start_time)
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([$exam_id, $user_id]);
        return $this->conn->lastInsertId();
    }
    
    public function submitAnswer($attempt_id, $question_id, $answer) {
        $stmt = $this->conn->prepare("
            SELECT type FROM exam_questions WHERE id = ?
        ");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $is_correct = false;
        $points_earned = 0;
        
        if ($question['type'] === 'multiple_choice') {
            $stmt = $this->conn->prepare("
                SELECT is_correct FROM question_options 
                WHERE id = ? AND question_id = ?
            ");
            $stmt->execute([$answer, $question_id]);
            $option = $stmt->fetch(PDO::FETCH_ASSOC);
            $is_correct = $option['is_correct'];
        } elseif ($question['type'] === 'true_false') {
            $stmt = $this->conn->prepare("
                SELECT option_text FROM question_options 
                WHERE question_id = ? AND is_correct = TRUE
            ");
            $stmt->execute([$question_id]);
            $correct_answer = $stmt->fetch(PDO::FETCH_ASSOC);
            $is_correct = $answer === $correct_answer['option_text'];
        }
        
        if ($is_correct) {
            $stmt = $this->conn->prepare("
                SELECT points FROM exam_questions WHERE id = ?
            ");
            $stmt->execute([$question_id]);
            $points_earned = $stmt->fetch(PDO::FETCH_ASSOC)['points'];
        }
        
        $stmt = $this->conn->prepare("
            INSERT INTO user_answers (attempt_id, question_id, answer, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $attempt_id,
            $question_id,
            $answer,
            $is_correct,
            $points_earned
        ]);
    }
    
    public function finishExam($attempt_id) {
        // Calcular puntaje total
        $stmt = $this->conn->prepare("
            SELECT SUM(points_earned) as total_score
            FROM user_answers
            WHERE attempt_id = ?
        ");
        $stmt->execute([$attempt_id]);
        $score = $stmt->fetch(PDO::FETCH_ASSOC)['total_score'];
        
        // Actualizar intento
        $stmt = $this->conn->prepare("
            UPDATE exam_attempts
            SET end_time = NOW(),
                score = ?,
                status = 'completed'
            WHERE id = ?
        ");
        
        return $stmt->execute([$score, $attempt_id]);
    }
    
    // Continúa con más métodos...
} 