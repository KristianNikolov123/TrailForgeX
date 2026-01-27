<?php

function render_pagination(
  int $totalItems,
  int $perPage,
  int $currentPage,
  string $baseUrl,
  array $extraQuery = [],
  int $window = 2
): string {
  if ($perPage <= 0) $perPage = 10;
  $totalPages = (int)ceil($totalItems / $perPage);

  if ($totalPages <= 1) return '';

  $currentPage = max(1, min($currentPage, $totalPages));

  $buildUrl = function(int $page) use ($baseUrl, $extraQuery) {
    $q = array_merge($extraQuery, ['page' => $page]);
    $qs = http_build_query($q);
    return $baseUrl . ($qs ? ('?' . $qs) : '');
  };

  $start = max(1, $currentPage - $window);
  $end   = min($totalPages, $currentPage + $window);

  // Expand to always show up to (window*2 + 1) pages when possible
  while (($end - $start) < ($window * 2) && ($start > 1 || $end < $totalPages)) {
    if ($start > 1) $start--;
    else if ($end < $totalPages) $end++;
    else break;
  }

  $html = '<nav class="pagination" aria-label="Pagination"><ul class="pagination-list">';

  // Prev
  $prevDisabled = ($currentPage <= 1);
  $html .= '<li class="pagination-item">';
  $html .= $prevDisabled
    ? '<span class="pagination-btn is-disabled">← Prev</span>'
    : '<a class="pagination-btn" href="'.htmlspecialchars($buildUrl($currentPage - 1)).'">← Prev</a>';
  $html .= '</li>';

  // First + ellipsis
  if ($start > 1) {
    $html .= '<li class="pagination-item"><a class="pagination-btn" href="'.htmlspecialchars($buildUrl(1)).'">1</a></li>';
    if ($start > 2) $html .= '<li class="pagination-item"><span class="pagination-ellipsis">…</span></li>';
  }

  // Window pages
  for ($p = $start; $p <= $end; $p++) {
    if ($p === $currentPage) {
      $html .= '<li class="pagination-item"><span class="pagination-btn is-active" aria-current="page">'.$p.'</span></li>';
    } else {
      $html .= '<li class="pagination-item"><a class="pagination-btn" href="'.htmlspecialchars($buildUrl($p)).'">'.$p.'</a></li>';
    }
  }

  // Last + ellipsis
  if ($end < $totalPages) {
    if ($end < $totalPages - 1) $html .= '<li class="pagination-item"><span class="pagination-ellipsis">…</span></li>';
    $html .= '<li class="pagination-item"><a class="pagination-btn" href="'.htmlspecialchars($buildUrl($totalPages)).'">'.$totalPages.'</a></li>';
  }

  // Next
  $nextDisabled = ($currentPage >= $totalPages);
  $html .= '<li class="pagination-item">';
  $html .= $nextDisabled
    ? '<span class="pagination-btn is-disabled">Next →</span>'
    : '<a class="pagination-btn" href="'.htmlspecialchars($buildUrl($currentPage + 1)).'">Next →</a>';
  $html .= '</li>';

  $html .= '</ul></nav>';

  return $html;
}
