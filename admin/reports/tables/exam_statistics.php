<table>
    <thead>
        <tr>
            <th>Examen</th>
            <th>Curso</th>
            <th>Total Intentos</th>
            <th>Promedio</th>
            <th>Aprobados</th>
            <th>Reprobados</th>
            <th>Tasa de Aprobación</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($report['data'] as $row): ?>
            <?php 
            $total = $row['passed_count'] + $row['failed_count'];
            $pass_rate = $total > 0 ? ($row['passed_count'] / $total) * 100 : 0;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['exam_title']); ?></td>
                <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                <td><?php echo $row['total_attempts']; ?></td>
                <td><?php echo number_format($row['average_score'], 1); ?></td>
                <td><?php echo $row['passed_count']; ?></td>
                <td><?php echo $row['failed_count']; ?></td>
                <td><?php echo number_format($pass_rate, 1); ?>%</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table> 