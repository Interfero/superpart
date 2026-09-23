-- Удаление учётки old_1_test@superpart.ru (id=1)

DELETE wri FROM withdrawal_request_items wri
INNER JOIN orders o ON o.id = wri.order_id
WHERE o.user_id = 1;

DELETE t FROM transactions t
INNER JOIN orders o ON o.id = t.order_id
WHERE o.user_id = 1;

DELETE r FROM reviews r
INNER JOIN orders o ON o.id = r.order_id
WHERE o.user_id = 1;

DELETE FROM sessions WHERE user_id = 1;
DELETE FROM reviews WHERE user_id = 1;
DELETE FROM transactions WHERE user_id = 1;
DELETE FROM withdrawal_requests WHERE user_id = 1;
DELETE FROM orders WHERE user_id = 1;
DELETE FROM employees WHERE user_id = 1;
DELETE FROM sources WHERE user_id = 1;
DELETE FROM partner_bank_cards WHERE user_id = 1;
DELETE FROM partner_phones WHERE user_id = 1;
DELETE FROM user_allowed_cities WHERE user_id = 1;
DELETE FROM user_allowed_sources WHERE user_id = 1;
DELETE FROM user_allowed_reference_sources WHERE user_id = 1;
UPDATE users SET parent_user_id = NULL WHERE parent_user_id = 1;
DELETE FROM users WHERE id = 1;
