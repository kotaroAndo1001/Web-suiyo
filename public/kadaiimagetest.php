<?php
$dbh = new PDO('mysql:host=mysql;dbname=kadai_db', 'root', '');

if (isset($_POST['body'])) {
  // POSTで送られてくるフォームパラメータ body がある場合

  $image_filename = null;
  if (isset($_FILES['image']) && !empty($_FILES['image']['tmp_name'])) {
    // アップロードされた画像がある場合
    if (preg_match('/^image\//', mime_content_type($_FILES['image']['tmp_name'])) != 1) {
      // アップロードされたものが画像ではなかった場合処理を強制的に終了
      header("HTTP/1.1 302 Found");
      header("Location: ./kadaiimagetest.php");
      return;
    }

    // 元のファイル名から拡張子を取得
    $pathinfo = pathinfo($_FILES['image']['name']);
    $extension = $pathinfo['extension'];
    // 新しいファイル名を決める。他の投稿の画像ファイルと重複しないように時間+乱数で決める。
    $image_filename = strval(time()) . '.' . bin2hex(random_bytes(25)) . '.' . $extension;

    $filepath = '/var/www/upload/image/' . $image_filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $filepath);
  }

  // insertする
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (body, image_filename) VALUE (:body, :image_filename)");
  $insert_sth->execute([
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと，リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 302 Found");
  header("Location: ./kadaiimagetest.php");
  return;
}

// ▼▼▼ ここから「ページング対応」: 1ページ10件 ▼▼▼
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }

$count_per_page = 10;
$skip_count = $count_per_page * ($page - 1);

// 全件数
$count_sth = $dbh->query('SELECT COUNT(*) FROM bbs_entries;');
$count_all = (int)$count_sth->fetchColumn();
$total_pages = max(1, (int)ceil($count_all / $count_per_page));

// 存在しないページが指定されたら最終ページへ
if ($skip_count >= $count_all && $count_all > 0) {
  header("HTTP/1.1 302 Found");
  header("Location: ?page=" . $total_pages);
  return;
}

// いままで保存してきたものを取得（ページング版）
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
$select_sth->bindParam(':limit', $count_per_page, PDO::PARAM_INT);
$select_sth->bindParam(':offset', $skip_count, PDO::PARAM_INT);
$select_sth->execute();
?>

<!-- フォームのPOST先はこのファイル自身です -->
<form method="POST" action="./kadaiimagetest.php" enctype="multipart/form-data">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <button type="submit">送信</button>
</form>

<hr>

<!-- ===== ページ情報（上のみ表示） ===== -->
<div class="pager" style="max-width:600px;margin:10px auto 16px;text-align:center;">
  <div style="margin-bottom:8px;">
    <?= (int)$page ?> ページ目 / 全 <?= (int)$total_pages ?> ページ
  </div>
  <div style="display:flex;justify-content:space-between;">
    <div>
      <?php if ($page > 1): ?>
        <a href="?page=<?= (int)($page - 1) ?>">&larr; 前のページ</a>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($page < $total_pages): ?>
        <a href="?page=<?= (int)($page + 1) ?>">次のページ &rarr;</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>ID</dt>
    <dd><?= $entry['id'] ?></dd>
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= nl2br(htmlspecialchars($entry['body'])) ?>  <!-- 必ず htmlspecialchars() すること -->
      <?php if(!empty($entry['image_filename'])): ?>  <!-- 画像がある場合は img 要素を使って表示 -->
        <div>
          <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
        </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<link rel="stylesheet" href="./css/style.css">

<script>
// フォーム送信イベントに割り込む
document.querySelector("form").addEventListener("submit", e => {
  const input = document.getElementById("imageInput");
  if (!input.files.length) return;                // 画像がなければそのまま送信
  const file = input.files[0];
  if (!file.type.startsWith("image/")) return;    // 画像以外ならそのまま送信
  if (file.size <= 5*1024*1024) return;           // 5MB以下ならそのまま送信

  e.preventDefault();                             // 送信を止める

  // 選んだ画像をブラウザで読み込む
  const img = new Image();
  img.src = URL.createObjectURL(file);

  img.onload = () => {
    const max = 1000;                             // 縮小後の最大長辺サイズ
    let w = img.width, h = img.height;
    if (w > h && w > max) { h = h*max/w; w = max; }
    else if (h >= w && h > max) { w = w*max/h; h = max; }

    // Canvasに描画
    const cv = document.createElement("canvas");
    cv.width = w; cv.height = h;
    cv.getContext("2d").drawImage(img, 0, 0, w, h);

    // 元の形式 (file.type) のまま出力
    cv.toBlob(b => {
      if (!b || b.size > 5*1024*1024) {           // 縮小後でも大きすぎたら弾く
        alert("画像が大きすぎます");
        return;
      }

      // Fileを作り直して input.files を置き換え
      const dt = new DataTransfer();
      dt.items.add(new File([b], file.name, {type: file.type}));
      input.files = dt.files;

      e.target.submit();                          // 縮小版で再送信
    }, file.type); // ← JPEG固定じゃなく file.type をそのまま使う
  };
});
</script>
