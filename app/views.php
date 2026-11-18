<?php
declare(strict_types=1);

function pageHeader(string $title, string $active): void
{
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="CICADA — учебный книжный магазин. Классика, ужасы и фантастика.">
  <title>CICADA — <?= e($title) ?></title>
  <link rel="icon" href="images/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" href="images/favicon.png" sizes="64x64">
  <link rel="stylesheet" href="css/bootstrap.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/lab4.css">
  <script src="script.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержимому</a>
<header class="site-header">
  <div class="container site-container header-inner">
    <a class="navbar-brand brand" href="index.php" aria-label="CICADA — на главную">
      <img class="brand-logo" src="images/лого1.jpg" alt="Логотип CICADA — цикада" width="50" height="50">
      <span><span class="brand-name">CICADA</span><span class="brand-caption">Книжный магазин</span></span>
    </a>
    <nav aria-label="Основное меню"><ul class="nav site-nav">
      <?php foreach (['index.php' => 'Главная', 'catalog.php' => 'Каталог', 'order.php' => 'Заказать'] as $href => $label): ?>
      <li class="nav-item"><a class="nav-link<?= $active === $href ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $active === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul></nav>
  </div>
</header>
<?php
}

function pageFooter(): void
{
?>
<footer class="site-footer">
  <div class="container site-container row-gap-4 d-flex flex-wrap justify-content-between">
    <div><p class="footer-title">Книжный магазин «CICADA»</p><p class="mb-0">© 2026. Учебный проект.</p></div>
    <div><p class="footer-title">Контакты</p><a href="mailto:cicadabooks@example.com">cicadabooks@example.com</a><p class="mb-0 mt-2">Доставка и самовывоз</p></div>
  </div>
</footer>
</body>
</html>
<?php
}

function bookCard(array $book): void
{
?>
<div class="col book-item" data-title="<?= e($book['title']) ?>" data-author="<?= e($book['author']) ?>" data-genres="<?= e($book['genre']) ?>" data-book-id="<?= (int) $book['id'] ?>">
  <article class="card book-card h-100">
    <a class="book-cover-area" href="book.php?id=<?= (int) $book['id'] ?>" aria-label="Подробнее о книге «<?= e($book['title']) ?>»">
      <img src="<?= e($book['image']) ?>" alt="Обложка книги «<?= e($book['title']) ?>»" width="130" height="176" loading="lazy">
    </a>
    <div class="card-body d-flex flex-column align-items-start">
      <h3 class="card-title"><?= e($book['title']) ?></h3>
      <p class="book-author"><?= e($book['author']) ?></p>
      <p class="book-price"><?= e(money($book['price'])) ?></p>
      <a class="btn btn-primary mt-auto" href="book.php?id=<?= (int) $book['id'] ?>">Подробнее<span class="visually-hidden">: <?= e($book['title']) ?></span></a>
    </div>
  </article>
</div>
<?php
}
