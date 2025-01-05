ALTER TABLE `velar_tickers`
ADD `velar_token_id` int unsigned NULL AFTER `id`,
ADD FOREIGN KEY (`velar_token_id`) REFERENCES `velar_tokens` (`id`) ON DELETE SET NULL;
