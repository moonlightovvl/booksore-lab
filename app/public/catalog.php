<?php
// catalog.html became catalog.php. Data comes from script.php and MySQL.
require_once dirname(__DIR__) . '/script.php';
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/views.php';
$books = getBooks();
pageHeader('Каталог', 'catalog.php');
?>
<main class="container site-container catalog-main" id="main">
  <h1>Каталог книг</h1>
  <form id="catalog-filter" class="row g-3 align-items-end mb-4" role="search">
    <div class="col-md-8 col-lg-9"><label class="form-label" for="search">Название книги или автор</label>
      <div class="input-group"><input class="form-control" id="search" name="search" type="search" placeholder="Название книги или автор"><button class="btn btn-primary" type="submit">Поиск</button></div>
    </div>
    <div class="col-md-4 col-lg-3"><label class="form-label" for="genre">Жанр</label><select class="form-select" id="genre" name="genre">
      <option value="">Все жанры</option><option>Классика</option><option>Ужасы</option><option>Фантастика</option>
    </select></div>
  </form>
  <p id="catalog-status" class="catalog-status" role="status">Найдено книг: <?= count($books) ?></p>
  <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
    <?php foreach ($books as $book): ?>
      <?php bookCard($book); ?>
    <?php endforeach; ?>
  </div>
  <p class="alert alert-light mt-4" id="no-results"<?= $books ? ' hidden' : '' ?>>Книги не найдены. Измените запрос или выберите другой жанр.</p>
  <noscript><p class="mt-3">Все книги доступны выше. Для поиска по каталогу включите JavaScript.</p></noscript>
</main>
<?php pageFooter(); ?>
