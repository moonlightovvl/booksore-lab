<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/views.php';
$books = getBooks(true);
pageHeader('Главная', 'index.php');
?>
<main class="container site-container home-main" id="main">
  <section class="hero row g-0 align-items-center" aria-labelledby="hero-title">
    <div class="col-md-8 hero-copy">
      <h1 id="hero-title">Истории для твоей полки</h1>
      <p>Классика, ужасы и фэнтези.<br>Выбери следующую историю в каталоге.</p>
      <a class="btn btn-primary btn-lg" href="catalog.php">В каталог</a>
    </div>
    <div class="col-md-4 hero-art"><a href="book.php?id=7" aria-label="Подробнее о книге «1984»"><img src="images/книга3.jpg" alt="Обложка романа «1984»" width="204" height="310"></a></div>
  </section>
  <section class="popular" aria-labelledby="popular-title">
    <h2 id="popular-title">Популярные книги</h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
      <?php foreach ($books as $book): bookCard($book); endforeach; ?>
    </div>
    <?php if (!$books): ?><p>Популярные книги появятся здесь позже.</p><?php endif; ?>
  </section>
  <p class="services">Доставка по городу · Самовывоз · Подбор книг</p>
</main>
<?php pageFooter(); ?>
