USE cicada_lab4;
SELECT id, book_id, book_title, unit_price, quantity, total, customer_name, email, phone, delivery, address, message, created_at
FROM feedback ORDER BY id DESC;
