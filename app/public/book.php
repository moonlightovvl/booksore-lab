<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/views.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$book = $id ? getBook($id) : null;
if (!$book) {
    http_response_code(404);
    pageHeader('Книга не найдена', 'catalog.php');
    echo '<main class="container site-container" id="main"><h1>Книга не найдена</h1><p>Вернитесь в <a href="catalog.php">каталог</a> и выберите доступную книгу.</p></main>';
    pageFooter();
    exit;
}
pageHeader($book['title'], 'catalog.php');
?>
<main class="container site-container" id="main">
  <nav aria-label="Хлебные крошки"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Главная</a></li><li class="breadcrumb-item"><a href="catalog.php">Каталог</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($book['title']) ?></li></ol></nav>
  <article class="row g-4 g-lg-5 book-detail" aria-labelledby="book-title">
    <div class="col-md-5 col-lg-4"><img class="detail-cover img-fluid" src="<?= e($book['image']) ?>" alt="Обложка книги «<?= e($book['title']) ?>»" width="360" height="550"></div>
    <div class="col-md-7 col-lg-8">
      <h1 id="book-title"><?= e($book['title']) ?></h1><p class="detail-author"><?= e($book['author']) ?></p>
      <p><span class="badge availability">В наличии</span></p><p class="detail-price"><?= e(money($book['price'])) ?></p>
      <p class="book-description"><?= e($book['description']) ?></p>
      <section class="characteristics" aria-labelledby="features-title"><h2 id="features-title">Характеристики</h2><dl class="row mb-0">
        <?php foreach (['Жанр' => 'genre', 'Язык' => 'language', 'Переплёт' => 'binding'] as $label => $key): ?>
          <dt class="col-sm-3"><?= e($label) ?></dt><dd class="col-sm-9"><?= e($book[$key]) ?></dd>
        <?php endforeach; ?>
      </dl></section>
      <div class="d-flex flex-wrap gap-3 mt-4"><a class="btn btn-primary btn-lg" href="order.php?book=<?= (int) $book['id'] ?>">Оформить заказ</a><a class="btn btn-secondary btn-lg" href="catalog.php">В каталог</a></div>
    </div>
  </article>
</main>
<?php pageFooter(); ?>
