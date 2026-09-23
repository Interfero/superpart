-- Удаление тестовых учёток 3, 4, 5 (+ дочерние 6, 7 при наличии)

DELETE wri FROM withdrawal_request_items wri
INNER JOIN orders o ON o.id = wri.order_id
WHERE o.user_id IN (3, 4, 5, 6, 7);

DELETE t FROM transactions t
INNER JOIN orders o ON o.id = t.order_id
WHERE o.user_id IN (3, 4, 5, 6, 7);

DELETE r FROM reviews r
INNER JOIN orders o ON o.id = r.order_id
WHERE o.user_id IN (3, 4, 5, 6, 7);

DELETE FROM sessions WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM reviews WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM transactions WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM withdrawal_requests WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM orders WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM employees WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM sources WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM partner_bank_cards WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM partner_phones WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM user_allowed_cities WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM user_allowed_sources WHERE user_id IN (3, 4, 5, 6, 7);
DELETE FROM user_allowed_reference_sources WHERE user_id IN (3, 4, 5, 6, 7);
UPDATE users SET parent_user_id = NULL WHERE parent_user_id IN (3, 4, 5, 6, 7);
DELETE FROM users WHERE id IN (3, 4, 5, 6, 7);
