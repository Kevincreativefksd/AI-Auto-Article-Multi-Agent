<?php
if (!defined('ABSPATH')) exit;

/**
 * PHP Image Renderer untuk Visual Content Agent.
 *
 * Mengubah data visual (hasil validasi) menjadi gambar editorial 1920x1080 secara lokal.
 * Tidak memakai API image generation, browser headless, gambar stok, atau URL eksternal.
 *
 * Mesin utama : PHP GD + FreeType (font TTF lokal di folder /fonts).
 * Cadangan    : Imagick, hanya bila GD (dengan FreeType) tidak tersedia.
 * Format      : WebP diutamakan, JPEG sebagai alternatif. Ukuran dioptimasi bertahap
 *               dengan menurunkan kualitas (resolusi tidak diturunkan).
 *
 * Hasil encode ditampung di memori (ukurannya hanya puluhan KB) sehingga tidak ada
 * file sementara yang perlu dibersihkan; berkas final ditulis sekali oleh agen
 * setelah optimasi selesai.
 */
class AAA_Visual_Renderer {

    const WIDTH  = 1920;
    const HEIGHT = 1080;
    const MARGIN = 120;

    // Sisa memori minimum (byte) yang dibutuhkan agar rendering aman: kanvas 1920x1080 TrueColor ~8 MB.
    const MIN_FREE_MEMORY = 41943040; // 40 MB

    // Tinggi huruf "H" (piksel) pada font Liberation Sans ukuran 100 px. Dipakai untuk kalibrasi satuan font GD.
    const CAP_H_AT_100PX = 69;

    // Tangga kualitas (diurutkan dari tertinggi). Batas bawah sengaja dijaga supaya teks tetap tajam.
    private static $ladder_webp = [90, 82, 74, 66, 58, 50, 42];
    private static $ladder_jpeg = [90, 85, 80, 75, 70, 65, 60, 55];

    private static $pt_per_px = null;

    // ---------------------------------------------------------------------
    // Kemampuan server
    // ---------------------------------------------------------------------

    public static function capabilities() {
        $gd = function_exists('imagecreatetruecolor') && function_exists('imagettftext') && function_exists('imagettfbbox');
        $info = function_exists('gd_info') ? gd_info() : [];
        if ($gd && isset($info['FreeType Support']) && !$info['FreeType Support']) {
            $gd = false;
        }
        $gd_webp = $gd && function_exists('imagewebp') && (!isset($info['WebP Support']) || !empty($info['WebP Support']));
        $gd_jpeg = $gd && function_exists('imagejpeg');

        $imagick = false;
        $im_webp = false;
        $im_jpeg = false;
        if (class_exists('Imagick')) {
            try {
                $imagick = true;
                $im_webp = count(Imagick::queryFormats('WEBP')) > 0;
                $im_jpeg = count(Imagick::queryFormats('JPEG')) > 0;
            } catch (\Throwable $e) {
                $imagick = false;
                $im_webp = false;
                $im_jpeg = false;
            }
        }

        return [
            'gd'         => $gd,
            'gd_webp'    => $gd_webp,
            'gd_jpeg'    => $gd_jpeg,
            'imagick'    => $imagick,
            'imagick_webp' => $im_webp,
            'imagick_jpeg' => $im_jpeg,
            'wp_webp'    => self::wp_allows_webp(),
            'fonts'      => is_readable(self::font_path('bold')) && is_readable(self::font_path('regular')),
        ];
    }

    private static function wp_allows_webp() {
        if (function_exists('get_allowed_mime_types')) {
            return in_array('image/webp', get_allowed_mime_types(), true);
        }
        return true;
    }

    public static function font_path($weight) {
        $file = ($weight === 'bold') ? 'LiberationSans-Bold.ttf' : 'LiberationSans-Regular.ttf';
        return __DIR__ . '/fonts/' . $file;
    }

    private static function memory_ok() {
        $raw = @ini_get('memory_limit');
        if ($raw === false || $raw === '' || $raw === null) return true;
        $limit = function_exists('wp_convert_hr_to_bytes') ? wp_convert_hr_to_bytes($raw) : (int) $raw;
        if ((int) $limit <= 0) return true; // -1 = tanpa batas
        return ($limit - memory_get_usage(true)) >= self::MIN_FREE_MEMORY;
    }

    // ---------------------------------------------------------------------
    // Titik masuk
    // ---------------------------------------------------------------------

    /**
     * Render gambar dari data visual.
     *
     * @param array $visual brand_name, category_label, article_title, brand_description,
     *                      accent_color, background_color, text_color, image{format,max_kb}
     * @return array|WP_Error ['binary','mime','ext','format','quality','bytes','width','height','optimized','backend','note']
     */
    public static function render_visual_image($visual) {
        if (!is_array($visual)) {
            return new WP_Error('visual_bad_input', 'Data visual tidak valid.');
        }

        $caps = self::capabilities();
        if (!$caps['fonts']) {
            return new WP_Error('visual_no_font', 'Berkas font lokal tidak ditemukan di folder fonts.');
        }
        if (!$caps['gd'] && !$caps['imagick']) {
            return new WP_Error('visual_no_renderer', 'Server tidak memiliki PHP GD (FreeType) maupun Imagick.');
        }
        if (!self::memory_ok()) {
            return new WP_Error('visual_low_memory', 'Memori server tidak cukup untuk merender gambar; langkah gambar dilewati.');
        }

        $d = self::normalize($visual);

        // Urutan format yang dicoba.
        $pref  = $d['format'];
        $order = ($pref === 'jpeg') ? ['jpeg'] : ['webp', 'jpeg'];

        $notes = [];
        if ($caps['gd']) {
            $backend = 'gd';
        } else {
            $backend = 'imagick';
            $notes[] = 'GD/FreeType tidak tersedia, memakai Imagick.';
        }

        try {
            $result = ($backend === 'gd')
                ? self::render_gd($d, $order, $caps, $notes)
                : self::render_imagick($d, $order, $caps, $notes);
        } catch (\Throwable $e) {
            return new WP_Error('visual_render_exception', 'Renderer gagal: ' . $e->getMessage());
        }

        if (is_wp_error($result)) return $result;

        $result['backend'] = $backend;
        $result['width']   = self::WIDTH;
        $result['height']  = self::HEIGHT;
        $result['note']    = implode(' ', $notes);
        return $result;
    }

    // ---------------------------------------------------------------------
    // Normalisasi masukan
    // ---------------------------------------------------------------------

    private static function normalize($v) {
        $bg = self::hex(isset($v['background_color']) ? $v['background_color'] : '', '#F4F2EE');
        $fg = self::hex(isset($v['text_color']) ? $v['text_color'] : '', '#171717');
        $ac = self::hex(isset($v['accent_color']) ? $v['accent_color'] : '', '#B92E32');

        // Kontras terlalu rendah membuat teks tak terbaca: kembali ke warna bawaan.
        if (self::contrast($bg, $fg) < 3.0) {
            $bg = '#F4F2EE';
            $fg = '#171717';
        }

        $image  = (isset($v['image']) && is_array($v['image'])) ? $v['image'] : [];
        $format = isset($image['format']) ? strtolower((string) $image['format']) : 'auto';
        if (!in_array($format, ['auto', 'webp', 'jpeg'], true)) $format = 'auto';
        $max_kb = isset($image['max_kb']) ? (int) $image['max_kb'] : 200;
        if ($max_kb < 20) $max_kb = 200;

        return [
            'brand'    => self::upper(self::clean_text(isset($v['brand_name']) ? $v['brand_name'] : '', 60)),
            'category' => self::upper(self::clean_text(isset($v['category_label']) ? $v['category_label'] : '', 40)),
            'title'    => self::clean_text(isset($v['article_title']) ? $v['article_title'] : '', 200),
            'desc'     => self::clean_text(isset($v['brand_description']) ? $v['brand_description'] : '', 120),
            'bg'       => $bg,
            'fg'       => $fg,
            'accent'   => $ac,
            'rule'     => self::mix_hex($bg, $fg, 0.5),
            'format'   => $format,
            'max_bytes'=> $max_kb * 1024,
        ];
    }

    private static function hex($value, $default) {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $value, $m)) {
            $c = $m[1];
            $value = '#' . $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
        }
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }
        return $default;
    }

    private static function rgb($hex) {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function mix_hex($a, $b, $t) {
        $ra = self::rgb($a);
        $rb = self::rgb($b);
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $out .= sprintf('%02X', (int) round($ra[$i] * (1 - $t) + $rb[$i] * $t));
        }
        return $out;
    }

    private static function luminance($hex) {
        $c = self::rgb($hex);
        foreach ($c as $i => $v) {
            $v = $v / 255;
            $c[$i] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function contrast($a, $b) {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        $hi = max($la, $lb);
        $lo = min($la, $lb);
        return ($hi + 0.05) / ($lo + 0.05);
    }

    private static function upper($s) {
        return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
    }

    private static function chars($s) {
        $parts = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($parts) ? $parts : [];
    }

    /**
     * Bersihkan teks untuk dirender: tanpa tag, tanpa karakter di luar jangkauan glyph font
     * (mencegah kotak "tofu"), spasi dirapikan, panjang dibatasi.
     */
    private static function clean_text($s, $max) {
        $s = is_scalar($s) ? (string) $s : '';
        $s = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($s) : strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES, 'UTF-8');
        $t = preg_replace('/[^\x{0020}-\x{007E}\x{00A0}-\x{024F}\x{2010}-\x{2027}\x{2030}-\x{203A}\x{20AC}\x{2122}]/u', ' ', $s);
        if (!is_string($t)) return '';
        $t = preg_replace('/\s+/u', ' ', $t);
        if (!is_string($t)) return '';
        $t = trim($t);
        $chars = self::chars($t);
        if (count($chars) > $max) {
            $t = rtrim(implode('', array_slice($chars, 0, $max)));
        }
        return $t;
    }

    // ---------------------------------------------------------------------
    // Mesin tata letak (independen dari GD/Imagick)
    // ---------------------------------------------------------------------

    /**
     * Hitung seluruh elemen gambar. $measure(teks, ukuran_px, 'bold'|'regular') mengembalikan lebar piksel.
     * Hasil: daftar primitif ['t'=>'rect'|'text', ...] dengan koordinat piksel.
     */
    private static function layout($d, $measure) {
        $W = self::WIDTH;
        $M = self::MARGIN;
        $inner = $W - 2 * $M;
        $prim = [];

        // Latar
        $prim[] = ['t' => 'rect', 'x' => 0, 'y' => 0, 'w' => $W, 'h' => self::HEIGHT, 'c' => $d['bg']];

        // Bagian atas: nama brand + garis pemisah tipis
        $brand = self::fit_line($d['brand'], 30, 'bold', $inner, $measure, 6.6);
        foreach (self::spaced($brand, 30, 'bold', 6.6, $M, 112, $d['fg'], $measure) as $p) $prim[] = $p;
        $prim[] = ['t' => 'rect', 'x' => $M, 'y' => 148, 'w' => $inner, 'h' => 2, 'c' => $d['rule']];

        // Garis aksen kecil + label kategori
        $prim[] = ['t' => 'rect', 'x' => $M, 'y' => 200, 'w' => 96, 'h' => 8, 'c' => $d['accent']];
        $cat = self::fit_line($d['category'], 28, 'bold', $inner, $measure, 6.2);
        foreach (self::spaced($cat, 28, 'bold', 6.2, $M, 262, $d['fg'], $measure) as $p) $prim[] = $p;

        // Judul: cari ukuran font terbesar yang muat tanpa terpotong
        $zone_top = 330;
        $zone_bottom = 890;
        $gap = 56;      // jarak judul ke garis aksen merah
        $rule_h = 8;
        $chosen = null;
        for ($size = 136; $size >= 44; $size -= 4) {
            $lines = self::wrap($d['title'], $size, $inner, $measure);
            $max_lines = ($size >= 64) ? 4 : 6;
            $lh = (int) round($size * 1.14);
            $block = $lh * (count($lines) - 1) + $size;
            $need = $block + $gap + $rule_h;
            if (count($lines) <= $max_lines && $need <= ($zone_bottom - $zone_top)) {
                $chosen = [$size, $lines, $lh, $block, $need];
                break;
            }
        }
        if ($chosen === null) {
            // Cadangan terakhir (judul amat panjang): ukuran minimum, baris berlebih dipangkas dengan elipsis.
            $size = 44;
            $lines = self::wrap($d['title'], $size, $inner, $measure);
            $lh = (int) round($size * 1.14);
            $fit = (int) floor((($zone_bottom - $zone_top) - $gap - $rule_h - $size) / $lh) + 1;
            if ($fit < 1) $fit = 1;
            if (count($lines) > $fit) {
                $lines = array_slice($lines, 0, $fit);
                $lines[$fit - 1] = self::fit_line(rtrim($lines[$fit - 1], '. ') . '...', $size, 'bold', $inner, $measure, 0);
            }
            $block = $lh * (count($lines) - 1) + $size;
            $chosen = [$size, $lines, $lh, $block, $block + $gap + $rule_h];
        }
        list($size, $lines, $lh, $block, $need) = $chosen;
        $top = $zone_top + (int) floor((($zone_bottom - $zone_top) - $need) / 2);
        $y = $top + (int) round($size * 0.9);
        foreach ($lines as $line) {
            $prim[] = ['t' => 'text', 'x' => $M, 'y' => $y, 's' => $line, 'z' => $size, 'f' => 'bold', 'c' => $d['fg']];
            $y += $lh;
        }

        // Garis aksen merah sebagai pemisah di bawah judul
        $prim[] = ['t' => 'rect', 'x' => $M, 'y' => $top + $block + $gap, 'w' => 180, 'h' => $rule_h, 'c' => $d['accent']];

        // Bagian bawah: garis tipis + deskripsi brand
        $prim[] = ['t' => 'rect', 'x' => $M, 'y' => 950, 'w' => $inner, 'h' => 2, 'c' => $d['rule']];
        $desc = self::fit_line($d['desc'], 30, 'regular', $inner, $measure, 0);
        if ($desc !== '') {
            $prim[] = ['t' => 'text', 'x' => $M, 'y' => 1008, 's' => $desc, 'z' => 30, 'f' => 'regular', 'c' => $d['fg']];
        }

        return $prim;
    }

    /** Potong teks (dengan elipsis) bila lebih lebar dari area. */
    private static function fit_line($text, $size, $font, $max_w, $measure, $spacing) {
        $chars = self::chars($text);
        $n = count($chars);
        if ($n === 0) return '';
        $w = call_user_func($measure, $text, $size, $font) + $spacing * ($n - 1);
        if ($w <= $max_w) return $text;
        while ($n > 1) {
            $n--;
            $cand = rtrim(implode('', array_slice($chars, 0, $n))) . '...';
            $w = call_user_func($measure, $cand, $size, $font) + $spacing * (self::len($cand) - 1);
            if ($w <= $max_w) return $cand;
        }
        return '';
    }

    private static function len($s) {
        return count(self::chars($s));
    }

    /** Pembungkusan kata (greedy). Kata yang lebih lebar dari area dipecah per huruf. */
    private static function wrap($text, $size, $max_w, $measure) {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || !$words) return [''];
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            // Pecah kata yang tidak muat satu baris penuh.
            while (call_user_func($measure, $word, $size, 'bold') > $max_w) {
                $chars = self::chars($word);
                $cut = count($chars) - 1;
                while ($cut > 1 && call_user_func($measure, implode('', array_slice($chars, 0, $cut)), $size, 'bold') > $max_w) {
                    $cut--;
                }
                if ($cut < 1) $cut = 1; // pengaman: selalu maju minimal satu huruf
                if ($cur !== '') {
                    $lines[] = $cur;
                    $cur = '';
                }
                $lines[] = implode('', array_slice($chars, 0, $cut));
                $word = implode('', array_slice($chars, $cut));
                if ($word === '') break;
            }
            if ($word === '') continue;
            $try = ($cur === '') ? $word : $cur . ' ' . $word;
            if (call_user_func($measure, $try, $size, 'bold') <= $max_w) {
                $cur = $try;
            } else {
                if ($cur !== '') $lines[] = $cur;
                $cur = $word;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines ? $lines : [''];
    }

    /** Teks huruf kapital ber-letter-spacing: dipecah jadi primitif per huruf. */
    private static function spaced($text, $size, $font, $spacing, $x, $y, $color, $measure) {
        $out = [];
        $chars = self::chars($text);
        $prefix = '';
        foreach ($chars as $i => $ch) {
            // Selisih lebar (awalan + huruf) - (huruf): tetap benar untuk spasi di akhir awalan
            // dan sudah memperhitungkan kerning.
            $offset = ($prefix === '') ? 0 : call_user_func($measure, $prefix . $ch, $size, $font) - call_user_func($measure, $ch, $size, $font);
            if ($ch !== ' ') {
                $out[] = ['t' => 'text', 'x' => (int) round($x + $offset + $i * $spacing), 'y' => $y, 's' => $ch, 'z' => $size, 'f' => $font, 'c' => $color];
            }
            $prefix .= $ch;
        }
        return $out;
    }

    // ---------------------------------------------------------------------
    // Mesin GD
    // ---------------------------------------------------------------------

    /**
     * Konversi piksel -> satuan "size" imagettftext. libgd memakai point pada 96 dpi (px = pt * 4/3),
     * sebagian build memakai 72 dpi. Dikalibrasi sekali per request dengan mengukur tinggi huruf "H".
     */
    private static function pt_per_px() {
        if (self::$pt_per_px !== null) return self::$pt_per_px;
        $k = 0.75;
        $bb = @imagettfbbox(75, 0, self::font_path('bold'), 'H');
        if (is_array($bb)) {
            $h = abs($bb[7] - $bb[1]);
            if ($h > 0) {
                $px_at_75 = 100 * $h / self::CAP_H_AT_100PX; // piksel hasil untuk size=75
                $calc = 75 / $px_at_75;
                if (abs($calc - 0.75) < 0.1)      $k = 0.75;
                elseif (abs($calc - 1.0) < 0.1)   $k = 1.0;
                elseif ($calc > 0.3 && $calc < 1.5) $k = $calc;
            }
        }
        self::$pt_per_px = $k;
        return $k;
    }

    private static function render_gd($d, $order, $caps, &$notes) {
        $k = self::pt_per_px();
        $fonts = ['bold' => self::font_path('bold'), 'regular' => self::font_path('regular')];

        $measure = function ($text, $size, $font) use ($k, $fonts) {
            $bb = @imagettfbbox($size * $k, 0, $fonts[$font], $text);
            if (!is_array($bb)) return 0;
            return abs($bb[2] - $bb[0]);
        };

        $prims = self::layout($d, $measure);

        $im = @imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if (!$im) {
            return new WP_Error('visual_gd_canvas', 'GD gagal membuat kanvas gambar.');
        }

        $colors = [];
        $alloc = function ($hex) use ($im, &$colors) {
            if (!isset($colors[$hex])) {
                $c = self::rgb($hex);
                $colors[$hex] = imagecolorallocate($im, $c[0], $c[1], $c[2]);
            }
            return $colors[$hex];
        };

        foreach ($prims as $p) {
            if ($p['t'] === 'rect') {
                imagefilledrectangle($im, $p['x'], $p['y'], $p['x'] + $p['w'] - 1, $p['y'] + $p['h'] - 1, $alloc($p['c']));
            } else {
                imagettftext($im, $p['z'] * $k, 0, $p['x'], $p['y'], $alloc($p['c']), $fonts[$p['f']], $p['s']);
            }
        }
        unset($prims);

        $result = null;
        foreach ($order as $fmt) {
            if ($fmt === 'webp') {
                if (!$caps['gd_webp'] || !$caps['wp_webp']) {
                    $notes[] = 'WebP tidak didukung server/WordPress, memakai JPEG.';
                    continue;
                }
            } elseif (!$caps['gd_jpeg']) {
                continue;
            }
            $try = self::gd_optimize($im, $fmt, $d['max_bytes']);
            if ($try === null) {
                $notes[] = strtoupper($fmt) . ' gagal di-encode oleh GD' . ($fmt === 'webp' ? ', mencoba JPEG.' : '.');
                continue;
            }
            $result = $try;
            break;
        }
        imagedestroy($im);

        if ($result === null) {
            return new WP_Error('visual_encode_failed', 'Gagal meng-encode gambar ke WebP maupun JPEG. ' . implode(' ', $notes));
        }
        if (!$result['optimized']) {
            $notes[] = 'Ukuran tetap melebihi batas setelah kualitas minimum; hasil terbaik disimpan (' . $result['bytes'] . ' byte).';
        }
        return $result;
    }

    private static function gd_optimize($im, $format, $max_bytes) {
        $ladder = ($format === 'webp') ? self::$ladder_webp : self::$ladder_jpeg;
        if ($format === 'jpeg') {
            imageinterlace($im, true); // JPEG progresif: biasanya lebih kecil
        }
        $best = null;
        foreach ($ladder as $q) {
            ob_start();
            $ok = ($format === 'webp') ? @imagewebp($im, null, $q) : @imagejpeg($im, null, $q);
            $bin = ob_get_clean();
            if (!$ok || !is_string($bin) || $bin === '') {
                break;
            }
            $len = strlen($bin);
            $best = [
                'binary'    => $bin,
                'mime'      => ($format === 'webp') ? 'image/webp' : 'image/jpeg',
                'ext'       => ($format === 'webp') ? 'webp' : 'jpg',
                'format'    => $format,
                'quality'   => $q,
                'bytes'     => $len,
                'optimized' => ($len <= $max_bytes),
            ];
            if ($len <= $max_bytes) {
                return $best;
            }
            $bin = null;
        }
        return $best; // null bila encode pertama langsung gagal; selain itu hasil terkecil
    }

    // ---------------------------------------------------------------------
    // Mesin Imagick (cadangan)
    // ---------------------------------------------------------------------

    private static function render_imagick($d, $order, $caps, &$notes) {
        $fonts = ['bold' => self::font_path('bold'), 'regular' => self::font_path('regular')];

        $probe = new ImagickDraw();
        $measure = function ($text, $size, $font) use ($probe, $fonts) {
            $probe->setFont($fonts[$font]);
            $probe->setFontSize($size);
            $img = new Imagick();
            $img->newImage(8, 8, new ImagickPixel('#FFFFFF'));
            $m = $img->queryFontMetrics($probe, $text);
            $img->clear();
            return isset($m['textWidth']) ? (float) $m['textWidth'] : 0;
        };

        $prims = self::layout($d, $measure);

        $im = new Imagick();
        $im->newImage(self::WIDTH, self::HEIGHT, new ImagickPixel($d['bg']));
        foreach ($prims as $p) {
            $draw = new ImagickDraw();
            $draw->setFillColor(new ImagickPixel($p['c']));
            if ($p['t'] === 'rect') {
                $draw->rectangle($p['x'], $p['y'], $p['x'] + $p['w'] - 1, $p['y'] + $p['h'] - 1);
                $im->drawImage($draw);
            } else {
                $draw->setFont($fonts[$p['f']]);
                $draw->setFontSize($p['z']);
                $im->annotateImage($draw, $p['x'], $p['y'], 0, $p['s']);
            }
            $draw->clear();
        }
        unset($prims);
        $probe->clear();

        $result = null;
        foreach ($order as $fmt) {
            if ($fmt === 'webp') {
                if (!$caps['imagick_webp'] || !$caps['wp_webp']) {
                    $notes[] = 'WebP tidak didukung Imagick/WordPress, memakai JPEG.';
                    continue;
                }
            } elseif (!$caps['imagick_jpeg']) {
                continue;
            }
            $try = self::imagick_optimize($im, $fmt, $d['max_bytes']);
            if ($try === null) {
                $notes[] = strtoupper($fmt) . ' gagal di-encode oleh Imagick' . ($fmt === 'webp' ? ', mencoba JPEG.' : '.');
                continue;
            }
            $result = $try;
            break;
        }
        $im->clear();

        if ($result === null) {
            return new WP_Error('visual_encode_failed', 'Gagal meng-encode gambar ke WebP maupun JPEG (Imagick). ' . implode(' ', $notes));
        }
        if (!$result['optimized']) {
            $notes[] = 'Ukuran tetap melebihi batas setelah kualitas minimum; hasil terbaik disimpan (' . $result['bytes'] . ' byte).';
        }
        return $result;
    }

    private static function imagick_optimize($im, $format, $max_bytes) {
        $ladder = ($format === 'webp') ? self::$ladder_webp : self::$ladder_jpeg;
        $best = null;
        foreach ($ladder as $q) {
            try {
                $copy = clone $im;
                $copy->setImageFormat($format === 'webp' ? 'webp' : 'jpeg');
                $copy->setImageCompressionQuality($q);
                $copy->stripImage();
                if ($format === 'jpeg') {
                    $copy->setInterlaceScheme(Imagick::INTERLACE_PLANE);
                }
                $bin = $copy->getImageBlob();
                $copy->clear();
            } catch (\Throwable $e) {
                break;
            }
            if (!is_string($bin) || $bin === '') break;
            $len = strlen($bin);
            $best = [
                'binary'    => $bin,
                'mime'      => ($format === 'webp') ? 'image/webp' : 'image/jpeg',
                'ext'       => ($format === 'webp') ? 'webp' : 'jpg',
                'format'    => $format,
                'quality'   => $q,
                'bytes'     => $len,
                'optimized' => ($len <= $max_bytes),
            ];
            if ($len <= $max_bytes) return $best;
        }
        return $best;
    }
}
