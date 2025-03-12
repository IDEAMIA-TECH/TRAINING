<?php
class Pagination {
    private $total_records;
    private $records_per_page;
    private $current_page;
    private $total_pages;

    public function __construct($total_records, $records_per_page = 10) {
        $this->total_records = $total_records;
        $this->records_per_page = $records_per_page;
        $this->current_page = max(1, (int)($_GET['page'] ?? 1));
        $this->total_pages = ceil($total_records / $records_per_page);
    }

    public function getOffset() {
        return ($this->current_page - 1) * $this->records_per_page;
    }

    public function getLimit() {
        return $this->records_per_page;
    }

    public function render($base_url) {
        if ($this->total_pages <= 1) {
            return '';
        }

        $html = '<nav aria-label="Navegación de páginas"><ul class="pagination justify-content-center">';

        // Botón anterior
        $prev_disabled = $this->current_page <= 1 ? ' disabled' : '';
        $prev_url = $this->getPageUrl($base_url, $this->current_page - 1);
        $html .= "<li class=\"page-item{$prev_disabled}\">
                    <a class=\"page-link\" href=\"{$prev_url}\" tabindex=\"-1\">Anterior</a>
                  </li>";

        // Números de página
        $start = max(1, $this->current_page - 2);
        $end = min($this->total_pages, $start + 4);
        $start = max(1, $end - 4);

        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $this->current_page ? ' active' : '';
            $url = $this->getPageUrl($base_url, $i);
            $html .= "<li class=\"page-item{$active}\">
                        <a class=\"page-link\" href=\"{$url}\">{$i}</a>
                     </li>";
        }

        // Botón siguiente
        $next_disabled = $this->current_page >= $this->total_pages ? ' disabled' : '';
        $next_url = $this->getPageUrl($base_url, $this->current_page + 1);
        $html .= "<li class=\"page-item{$next_disabled}\">
                    <a class=\"page-link\" href=\"{$next_url}\">Siguiente</a>
                  </li>";

        $html .= '</ul></nav>';

        return $html;
    }

    private function getPageUrl($base_url, $page) {
        $params = $_GET;
        $params['page'] = $page;
        return $base_url . '?' . http_build_query($params);
    }
} 