<?php
//SELECT childpost.* FROM wp_posts childpost INNER JOIN wp_postmeta parentmeta ON (childpost.ID=parentmeta.meta_value)
// WHERE parentmeta.meta_key='_thumbnail_id' AND parentmeta.post_id=332373;


ini_set('max_execution_time', -1);
ini_set('memory_limit', -1);

/*$sql = "SELECT p.ID, p.post_author,  p.post_date, p.post_content, p.post_title, p.post_status, p.post_name, p.post_type
FROM `wp_posts` as p where p.post_type = 'post' and 
                           (p.post_status = 'publish' or p.post_status = 'future' or p.post_status = 'draft' 
                                or p.post_status = 'pending' or p.post_status = 'auto-draft') order by p.ID;";*/

$DBuser = 'root';
$DBpass = $_ENV['MYSQL_ROOT_PASSWORD'];

$database = 'mysql:host=database:3306;dbname=wp_migration';
$dbh = new PDO($database, $DBuser, $DBpass, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
$sql = "SELECT * FROM `kl_users`";
$rows = $dbh->query($sql, PDO::FETCH_ASSOC)->fetchAll();
$users = [];
foreach ($rows as $row){
    $users[$row['ID']] = $row['user_email'];
}

$sql = "SELECT p.ID, p.post_author,  p.post_date, p.post_content, p.post_title, p.post_status, p.post_name, p.post_type
FROM `kl_posts` as p where p.post_type = 'post'  and 
                           (p.post_status = 'publish' or p.post_status = 'future' or p.post_status = 'draft' 
                                or p.post_status = 'pending' or p.post_status = 'auto-draft') order by p.ID";
$arr = [];
$rows = $dbh->query($sql, PDO::FETCH_ASSOC);
$rows = $rows->fetchAll();
//foreach($rows as $i => $row) {
    $chunks = array_chunk($rows, 1000);
    foreach ($chunks as $i =>  $chunk) {
        foreach ($chunk as $row) {
if(is_string($row['post_content']) && !empty($row['post_content'])){
    $sub = [
        'ID' => $row['ID'],
        'user_id'=>$row['post_author'],
        'user_email'=>$users[$row['post_author']],
        'title' => ['uk' => $row['post_title']],
        'slug' => ['uk' => $row['post_name']],
        'content' => ['uk' => $row['post_content']],
        'scheduled_at' => $row['post_date'],
        'contentHasVideo' => ['uk' => isContentVideo($row['post_content'])]
    ];
}


        if ($row['post_status'] == 'publish') {
            $sub['status'] = 'PUBLISHED';

        }else if($row['post_status'] == 'future'){
            $sub['status'] = 'SCHEDULE';
        } elseif (in_array($row['post_status'], ['draft', 'pending', 'auto-draft'])) {
            $sub['status'] = 'DRAFT';
        }

            $sql2 = "select meta_key, meta_value from kl_postmeta where post_id = {$row['ID']}";
            foreach ($dbh->query($sql2, PDO::FETCH_ASSOC) as $row2) {
                if ($row2['meta_key'] == '_infim_shortlink') {
                    $sub['slug_short'] = ['uk' => $row2['meta_value']];
                }

                if ($row2['meta_key'] == '_genesis_title') {
                    $sub['title_seo'] = ['uk' => $row2['meta_value']];
                }
                if ($row2['meta_key'] == '_genesis_description') {
                    $sub['description'] = ['uk' => $row2['meta_value']];
                }
            }
            $categories = [];
            $tags = [];
            $q3 = "SELECT r.object_id, r.term_taxonomy_id, kl_term_taxonomy.taxonomy FROM `kl_term_relationships` as r
INNER JOIN kl_term_taxonomy 
on kl_term_taxonomy.term_id = r.term_taxonomy_id
WHERE r.object_id = {$row['ID']};";

            foreach ($dbh->query($q3, PDO::FETCH_ASSOC) as $row3) {
                if ($row3['taxonomy'] == 'category') {
                    $sqlCategories = "SELECT terms.term_id, terms.name, terms.slug, termsTypes.taxonomy, meta.meta_key, meta.meta_value, termsTypes.parent FROM `kl_terms` as terms
    inner JOIN kl_term_taxonomy as termsTypes on termsTypes.term_id = terms.term_id 
    left JOIN kl_termmeta as meta on meta.term_id = terms.term_id 
where termsTypes.taxonomy = 'category' and terms.term_id = {$row3['term_taxonomy_id']}
order by terms.term_id;";

                    foreach ($dbh->query($sqlCategories, PDO::FETCH_ASSOC) as $rowCategories) {
                        $categories[] = $rowCategories['slug'];
                    }
                } else if ($row3['taxonomy'] == 'post_tag') {
                    $sqlTags = "SELECT terms.term_id, terms.name, terms.slug, termsTypes.taxonomy, meta.meta_key, meta.meta_value, termsTypes.parent FROM `kl_terms` as terms
    inner JOIN kl_term_taxonomy as termsTypes on termsTypes.term_id = terms.term_id 
    left JOIN kl_termmeta as meta on meta.term_id = terms.term_id 
where termsTypes.taxonomy = 'post_tag' and terms.term_id = {$row3['term_taxonomy_id']}
order by terms.term_id;";

                    foreach ($dbh->query($sqlTags, PDO::FETCH_ASSOC) as $rowTags) {
                        $tags[] = $rowTags['slug'];
                    }
                }
            }

            $q4 = "SELECT childpost.guid, parentmeta.meta_key, childpost.ID FROM kl_posts childpost 
    INNER JOIN kl_postmeta parentmeta ON (childpost.ID=parentmeta.meta_value) 
WHERE parentmeta.meta_key='_thumbnail_id' AND parentmeta.post_id={$row['ID']};";
            $row4 = $dbh->query($q4);
            $previewId = '';
            if ($row4 && $res = $row4->fetch(PDO::FETCH_ASSOC)) {

                $url = $res['guid'];
                $sub['preview'] = $url;
                $previewId = $res['ID'];
            }
            $part = '';

            if(isset($previewId) && !empty($previewId)){
                $part = "ID != $previewId and";
            }

            $q5 = "select guid from kl_posts where {$part} post_parent = {$row['ID']} and post_type = 'attachment'";


            $row5 = $dbh->query($q5);
//            $otherImages = [];
//            if ($row5) {
//                while ($res = $row5->fetch(PDO::FETCH_ASSOC)) {
//                    $url = $res['guid'];
//                    $otherImages[] = $url;
//                }
//            }
//
//            $sub['otherImages'] = $otherImages;
            $sub['categories'] = $categories;
            $sub['tags'] = $tags;
            $arr[] = $sub;
        }
        file_put_contents("posts{$i}.txt", json_encode($arr));
        $arr = [];


    }

function isContentVideo(string $content)
{

    $dom = new \DOMDocument;
    @$dom->loadHTML($content);
    $nodes = $dom->getElementsByTagName("iframe");
    $res = false;
    foreach ($nodes as $i => $node){
        if(preg_match("/(youtube\.com|youtu.be|vimeo\.com)/", $nodes[$i]->getAttribute('src'))){
            $res = true;
        }
    }
    return $res;

}