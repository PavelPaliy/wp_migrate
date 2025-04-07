<?php
$sql = "SELECT terms.term_id, terms.name, terms.slug, termsTypes.taxonomy, meta.meta_key, meta.meta_value, termsTypes.parent FROM `kl_terms` as terms
    inner JOIN kl_term_taxonomy as termsTypes on termsTypes.term_id = terms.term_id 
    left JOIN kl_termmeta as meta on meta.term_id = terms.term_id 
where termsTypes.taxonomy = 'category' 
order by terms.term_id;";
$DBuser = 'root';
$DBpass = $_ENV['MYSQL_ROOT_PASSWORD'];

$database = 'mysql:host=database:3306;dbname=wp_migration';
$dbh = new PDO($database, $DBuser, $DBpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
$arr = [];
foreach($dbh->query($sql, PDO::FETCH_ASSOC) as $row) {
   // if($row['meta_key'] === '_uastar-seo-head1') continue;
    if(!empty($row['meta_value'])){

        $row['meta_value'] = maybe_unserialize($row['meta_value']);
    }

    $subArr = ['name'=>['uk'=>$row['name']], 'slug'=>['uk'=>$row['slug']], 'id'=>$row['term_id'], 'parent'=>$row['parent']];
    if(is_array($row['meta_value'])){
        if(isset($row['meta_value']['doctitle'])){
            $subArr['name_seo'] = ['uk'=>$row['meta_value']['doctitle']];
        }
        if(isset($row['meta_value']['description'])){
            $subArr['description_seo'] = ['uk'=>$row['meta_value']['description']];
        }
    }
    $arr[$row['slug']] = $subArr;

}

$sql = "SELECT terms.term_id, terms.name, terms.slug, termsTypes.taxonomy, meta.meta_key, meta.meta_value, termsTypes.parent FROM `kl_terms` as terms
    inner JOIN kl_term_taxonomy as termsTypes on termsTypes.term_id = terms.term_id 
    left JOIN kl_termmeta as meta on meta.term_id = terms.term_id 
where termsTypes.taxonomy = 'category' and meta.meta_key = '_uastar-seo-head1'
order by terms.term_id;";
foreach($dbh->query($sql, PDO::FETCH_ASSOC) as $row) {

    foreach ($arr as $key => $item){
        if($item['id'] == $row['term_id']){
            $arr[$key]['h_1_seo'] =['uk'=>$row['meta_value']];
            break;
        }
    }

}

foreach ($arr as $index => $item){
    if($item['parent']>0){
        $parentId = $item['parent'];

        $parentArr = array_filter($arr, function ($arrItem) use($parentId){
           return $arrItem['id'] ===  $parentId;
        });

        $parentArr = array_values($parentArr);

        if(count($parentArr)===1){
            $arr[$index]['parent_slug'] = $parentArr[0]['slug'];
        }
    }
}
echo count($arr);
file_put_contents("categories.json", json_encode($arr));
function maybe_unserialize( $data ) {
    if ( is_serialized( $data ) ) { // Don't attempt to unserialize data that wasn't serialized going in.
        return @unserialize( trim( $data ) );
    }

    return $data;
}

function is_serialized( $data, $strict = true ) {
    // If it isn't a string, it isn't serialized.
    if ( ! is_string( $data ) ) {
        return false;
    }
    $data = trim( $data );
    if ( 'N;' === $data ) {
        return true;
    }
    if ( strlen( $data ) < 4 ) {
        return false;
    }
    if ( ':' !== $data[1] ) {
        return false;
    }
    if ( $strict ) {
        $lastc = substr( $data, -1 );
        if ( ';' !== $lastc && '}' !== $lastc ) {
            return false;
        }
    } else {
        $semicolon = strpos( $data, ';' );
        $brace     = strpos( $data, '}' );
        // Either ; or } must exist.
        if ( false === $semicolon && false === $brace ) {
            return false;
        }
        // But neither must be in the first X characters.
        if ( false !== $semicolon && $semicolon < 3 ) {
            return false;
        }
        if ( false !== $brace && $brace < 4 ) {
            return false;
        }
    }
    $token = $data[0];
    switch ( $token ) {
        case 's':
            if ( $strict ) {
                if ( '"' !== substr( $data, -2, 1 ) ) {
                    return false;
                }
            } elseif ( false === strpos( $data, '"' ) ) {
                return false;
            }
        // Or else fall through.
        case 'a':
        case 'O':
            return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';
            return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
    }
    return false;
}