はてなブログのindexing requestツール

サイトマップインデックスからサイトマップを取り出し、  
サイトマップからURLを取り出し、  
Google Indexing API投げるだけのPHPツール 

コマンドライン引数にサイトマップインデックスのURLか、サイトマップのURLを複数指定できます。
はてなブログのサイトマップの場合、URLパラメータに＆（アンパサンド）が入っているので、URL全体を""で囲む必要があります。


```bash
php publish_sitemap_to_indexing_api.php <sitemap_index_url> <sitemap_url> ...
```

サイトマップインデックスで見つかる全てのサイトマップを処理する場合
```
php publish_sitemap_to_indexing_api.php https://kanaxx.hatenablog.jp/sitemap_index.xml
```

6月と5月のサイトマップを処理する場合
```
php publish_sitemap_to_indexing_api.php "https://kanaxx.hatenablog.jp/sitemap_periodical.xml?year=2020&month=6" "https://kanaxx.hatenablog.jp/sitemap_periodical.xml?year=2020&month=5"
```

