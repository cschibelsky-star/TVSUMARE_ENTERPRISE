<?php
/** Bypass only the known Portal da Cidade image wrapper, preserving the original photo. */
function tvs_normalize_source_image_url(string $url): string {
    $url=trim($url);
    if(preg_match('~^https://image\\.portaldacidade\\.com/unsafe/[0-9]+x[0-9]+/(https://bucket\\.portaldacidade\\.com/[^?#]+\\.(?:jpg|jpeg|png|webp))(?:\\?[^#]*)?$~i',$url,$match))return $match[1];
    return $url;
}
