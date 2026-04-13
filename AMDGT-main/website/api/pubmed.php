<?php
/**
 * PubMed Evidence Proxy
 * Tìm kiếm bài báo Y khoa liên quan đến Thuốc-Bệnh từ NCBI PubMed
 */
require_once __DIR__ . '/../includes/config.php';

$drug = trim($_GET['drug'] ?? '');
$disease = trim($_GET['disease'] ?? '');

if (empty($drug) && empty($disease)) {
    jsonResponse(['error' => 'Cần ít nhất tên thuốc hoặc bệnh'], 400);
}

// Build search query
$terms = [];
if ($drug) $terms[] = urlencode($drug);
if ($disease) $terms[] = urlencode($disease);
$query = implode('+AND+', $terms);

// Call NCBI E-utilities (free, no API key needed for limited use)
$searchUrl = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&term={$query}&retmax=5&sort=relevance&retmode=json";

$ch = curl_init($searchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$searchResult = curl_exec($ch);
curl_close($ch);

if (!$searchResult) {
    jsonResponse(['articles' => [], 'message' => 'Không thể kết nối PubMed']);
}

$searchData = json_decode($searchResult, true);
$ids = $searchData['esearchresult']['idlist'] ?? [];

if (empty($ids)) {
    jsonResponse(['articles' => [], 'query' => "$drug $disease", 'total' => 0]);
}

// Fetch article details
$idStr = implode(',', $ids);
$fetchUrl = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&id={$idStr}&retmode=json";

$ch = curl_init($fetchUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$fetchResult = curl_exec($ch);
curl_close($ch);

$fetchData = json_decode($fetchResult, true);
$articles = [];

foreach ($ids as $pmid) {
    $item = $fetchData['result'][$pmid] ?? null;
    if ($item) {
        $authors = [];
        if (isset($item['authors'])) {
            foreach (array_slice($item['authors'], 0, 3) as $a) {
                $authors[] = $a['name'];
            }
        }
        $articles[] = [
            'pmid' => $pmid,
            'title' => $item['title'] ?? 'No title',
            'journal' => $item['fulljournalname'] ?? $item['source'] ?? '',
            'year' => substr($item['pubdate'] ?? '', 0, 4),
            'authors' => implode(', ', $authors) . (count($item['authors'] ?? []) > 3 ? ' et al.' : ''),
            'url' => "https://pubmed.ncbi.nlm.nih.gov/{$pmid}/",
            'doi' => $item['elocationid'] ?? ''
        ];
    }
}

$total = $searchData['esearchresult']['count'] ?? 0;
jsonResponse([
    'articles' => $articles,
    'query' => "$drug $disease",
    'total' => (int)$total
]);
?>
