<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Usuarios Activos</th>
            <th>Total Acciones</th>
            <th>Tipo de Acción</th>
            <th>Tipo de Entidad</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($report['data'] as $row): ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                <td><?php echo $row['active_users']; ?></td>
                <td><?php echo $row['total_actions']; ?></td>
                <td><?php echo ucfirst($row['action_type']); ?></td>
                <td><?php echo ucfirst($row['entity_type']); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table> 