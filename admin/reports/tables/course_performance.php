<table>
    <thead>
        <tr>
            <th>Curso</th>
            <th>Total Estudiantes</th>
            <th>Tasa de Completitud</th>
            <th>Promedio de Notas</th>
            <th>Tiempo Total (horas)</th>
            <th>Intentos de Examen</th>
            <th>Promedio Exámenes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($report['data'] as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                <td><?php echo $row['total_students']; ?></td>
                <td><?php echo number_format($row['completion_rate'], 1); ?>%</td>
                <td><?php echo number_format($row['average_score'], 1); ?></td>
                <td><?php echo $row['total_hours_spent']; ?></td>
                <td><?php echo $row['total_exam_attempts']; ?></td>
                <td><?php echo number_format($row['average_exam_score'], 1); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table> 