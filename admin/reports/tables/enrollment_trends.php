<table>
    <thead>
        <tr>
            <th>Período</th>
            <th>Total Inscripciones</th>
            <th>Cursos Únicos</th>
            <th>Usuarios Únicos</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($report['data'] as $row): ?>
            <tr>
                <td><?php echo $row['period']; ?></td>
                <td><?php echo $row['total_enrollments']; ?></td>
                <td><?php echo $row['unique_courses']; ?></td>
                <td><?php echo $row['unique_users']; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table> 