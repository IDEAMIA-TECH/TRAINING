<?php
class ExamManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function createExam($courseId, $data) {
        try {
            $this->conn->beginTransaction();
            
            // Insertar examen
            $stmt = $this->conn->prepare("
                INSERT INTO exams (
                    course_id, title, description, duration,
                    passing_score, attempts_allowed, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $courseId,
                $data['title'],
                $data['description'],
                $data['duration'],
                $data['passing_score'],
                $data['attempts_allowed'],
                $data['is_active'] ?? true
            ]);
            
            $examId = $this->conn->lastInsertId();
            
            // Insertar preguntas
            foreach ($data['questions'] as $index => $question) {
                $stmt = $this->conn->prepare("
                    INSERT INTO questions (
                        exam_id, question_type, question_text,
                        points, correct_answer, explanation, order_index
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $examId,
                    $question['type'],
                    $question['text'],
                    $question['points'],
                    $question['correct_answer'] ?? null,
                    $question['explanation'] ?? null,
                    $index + 1
                ]);
                
                $questionId = $this->conn->lastInsertId();
                
                // Si es opción múltiple, insertar opciones
                if ($question['type'] === 'multiple_choice' && !empty($question['options'])) {
                    foreach ($question['options'] as $optIndex => $option) {
                        $stmt = $this->conn->prepare("
                            INSERT INTO answer_options (
                                question_id, option_text, is_correct, order_index
                            ) VALUES (?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $questionId,
                            $option['text'],
                            $option['is_correct'] ?? false,
                            $optIndex + 1
                        ]);
                    }
                }
            }
            
            $this->conn->commit();
            return $examId;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    
    public function startExam($userId, $examId) {
        // Verificar intentos permitidos
        $stmt = $this->conn->prepare("
            SELECT e.*, COUNT(ea.id) as attempts_made
            FROM exams e
            LEFT JOIN exam_attempts ea ON ea.exam_id = e.id 
                AND ea.user_id = ? AND ea.status = 'completed'
            WHERE e.id = ?
            GROUP BY e.id
        ");
        $stmt->execute([$userId, $examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$exam) {
            throw new Exception("Examen no encontrado");
        }
        
        if ($exam['attempts_made'] >= $exam['attempts_allowed']) {
            throw new Exception("Has alcanzado el número máximo de intentos permitidos");
        }
        
        // Crear nuevo intento
        $stmt = $this->conn->prepare("
            INSERT INTO exam_attempts (user_id, exam_id, start_time)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $examId]);
        
        return $this->conn->lastInsertId();
    }
    
    public function submitAnswer($attemptId, $questionId, $answer) {
        // Verificar si el intento está activo
        $stmt = $this->conn->prepare("
            SELECT ea.*, e.duration
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            WHERE ea.id = ? AND ea.status = 'in_progress'
        ");
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attempt) {
            throw new Exception("Intento no válido o finalizado");
        }
        
        // Verificar tiempo límite
        $startTime = strtotime($attempt['start_time']);
        $timeLimit = $attempt['duration'] * 60;
        if (time() - $startTime > $timeLimit) {
            $this->finishAttempt($attemptId);
            throw new Exception("Tiempo límite excedido");
        }
        
        // Obtener pregunta
        $stmt = $this->conn->prepare("
            SELECT q.*, GROUP_CONCAT(
                CASE WHEN q.question_type = 'multiple_choice'
                THEN ao.id ELSE NULL END
            ) as correct_options
            FROM questions q
            LEFT JOIN answer_options ao ON ao.question_id = q.id AND ao.is_correct = TRUE
            WHERE q.id = ?
            GROUP BY q.id
        ");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Evaluar respuesta
        $isCorrect = false;
        $pointsEarned = 0;
        $feedback = '';
        
        switch ($question['question_type']) {
            case 'multiple_choice':
                $correctOptions = explode(',', $question['correct_options']);
                $isCorrect = count(array_diff($correctOptions, (array)$answer)) === 0 
                         && count(array_diff((array)$answer, $correctOptions)) === 0;
                break;
                
            case 'true_false':
                $isCorrect = $answer === $question['correct_answer'];
                break;
                
            case 'short_answer':
                $isCorrect = strtolower(trim($answer)) === strtolower(trim($question['correct_answer']));
                break;
                
            case 'essay':
                // Las preguntas de ensayo requieren revisión manual
                $isCorrect = null;
                break;
        }
        
        if ($isCorrect !== null) {
            $pointsEarned = $isCorrect ? $question['points'] : 0;
            $feedback = $isCorrect ? "¡Correcto!" : $question['explanation'];
        }
        
        // Guardar respuesta
        $stmt = $this->conn->prepare("
            INSERT INTO user_answers (
                attempt_id, question_id, answer_text,
                is_correct, points_earned, feedback
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $attemptId,
            $questionId,
            is_array($answer) ? json_encode($answer) : $answer,
            $isCorrect,
            $pointsEarned,
            $feedback
        ]);
        
        return [
            'is_correct' => $isCorrect,
            'points_earned' => $pointsEarned,
            'feedback' => $feedback
        ];
    }
    
    public function finishAttempt($attemptId) {
        // Calcular puntaje final
        $stmt = $this->conn->prepare("
            SELECT 
                SUM(ua.points_earned) as total_earned,
                SUM(q.points) as total_possible
            FROM user_answers ua
            JOIN questions q ON ua.question_id = q.id
            WHERE ua.attempt_id = ?
        ");
        $stmt->execute([$attemptId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $score = $result['total_possible'] > 0 
            ? round(($result['total_earned'] / $result['total_possible']) * 100) 
            : 0;
        
        // Actualizar intento
        $stmt = $this->conn->prepare("
            UPDATE exam_attempts 
            SET status = 'completed',
                end_time = NOW(),
                score = ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $attemptId]);
        
        return $score;
    }
    
    public function getExamResults($attemptId) {
        // Obtener información básica del intento
        $stmt = $this->conn->prepare("
            SELECT 
                ea.*,
                e.title as exam_title,
                e.passing_score,
                e.course_id,
                u.name as user_name,
                u.id as user_id
            FROM exam_attempts ea
            JOIN exams e ON ea.exam_id = e.id
            JOIN users u ON ea.user_id = u.id
            WHERE ea.id = ? AND ea.status = 'completed'
        ");
        $stmt->execute([$attemptId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("Resultados no encontrados");
        }
        
        // Obtener estadísticas
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT q.id) as total_questions,
                COUNT(DISTINCT CASE WHEN ua.is_correct THEN ua.id END) as correct_answers
            FROM questions q
            LEFT JOIN user_answers ua ON ua.question_id = q.id 
                AND ua.attempt_id = ?
            WHERE q.exam_id = ?
        ");
        $stmt->execute([$attemptId, $result['exam_id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $result = array_merge($result, $stats);
        
        // Obtener respuestas detalladas
        $stmt = $this->conn->prepare("
            SELECT 
                q.question_text,
                q.question_type,
                q.points,
                q.correct_answer,
                q.explanation,
                ua.answer_text,
                ua.is_correct,
                ua.points_earned
            FROM questions q
            LEFT JOIN user_answers ua ON ua.question_id = q.id 
                AND ua.attempt_id = ?
            WHERE q.exam_id = ?
            ORDER BY q.order_index
        ");
        $stmt->execute([$attemptId, $result['exam_id']]);
        $result['answers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    }
} 