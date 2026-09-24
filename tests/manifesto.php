<?php
/** Render the actual homepage against each shipped catalog: wp eval-file tests/manifesto.php */
if (!defined('ABSPATH')) { throw new RuntimeException('WordPress required.'); }
// CLI has no web-server request; supply the host used by WordPress header helpers.
$_SERVER['SERVER_NAME'] = wp_parse_url(home_url(), PHP_URL_HOST);
$expected = [
    'en_US' => ['Born into solitude.', 'Only our shadow stays forever.', 'Face every trial. Forge ahead. Make this life count.'],
    'zh_CN' => ['人生来孤独，', '唯有影为永恒伴侣，', '行者心态不虚此生。'],
    'de_DE' => ['In die Einsamkeit geboren.', 'Nur unser Schatten bleibt für immer.', 'Allen Widrigkeiten trotzen. Mutig vorangehen. Ein erfülltes Leben führen.'],
    'fr_FR' => ['Nous naissons seuls.', 'Seule notre ombre nous accompagne à jamais.', 'Affronter les épreuves. Avancer avec courage. Vivre pleinement.'],
    'es_ES' => ['Nacemos en soledad.', 'Solo nuestra sombra nos acompaña para siempre.', 'Afrontar la adversidad. Avanzar con valentía. Vivir con plenitud.'],
];
foreach ($expected as $locale => $lines) {
    unload_textdomain('shadowalker');
    if ($locale !== 'en_US') {
        load_textdomain('shadowalker', get_template_directory() . '/languages/' . $locale . '.mo', $locale);
    }
    ob_start();
    include get_template_directory() . '/front-page.php';
    $html = ob_get_clean();
    if (!preg_match('/<h1 id="manifesto-heading">(.*?)<\/h1>/s', $html, $heading)) {
        throw new RuntimeException('Missing locale-inheriting manifesto H1: ' . $locale);
    }
    preg_match_all('/<span[^>]*>(.*?)<\/span>/s', $heading[1], $matches);
    $actual = array_map(fn($text) => html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8'), $matches[1]);
    if ($actual !== $lines) { throw new RuntimeException('Incorrect manifesto: ' . $locale); }
    echo "PASS homepage manifesto: $locale\n";
}
