# JOIN View Project (PHP + MySQL)

This project compares **male vs female** mental health metrics per **social media platform** using **JOINs**.

## Files
- `config.php` — set your DB credentials (`db_name`, user, pass).
- `db.php` — initializes `$conn` (mysqli).
- `functions.php` — helper functions and SQL queries (JOIN and LEFT JOIN versions).
- `index.php` — UI with filters, loops, conditional formatting.
- `assets/styles.css` — basic styling.

## Requirements
- Two MySQL tables already imported:
  - `mental_health_male`
  - `mental_health_female`

## Run
1. Update `config.php` with your DB name and credentials.
2. Serve the folder via XAMPP/WAMP or `php -S localhost:8000`.
3. Visit `/index.php` in your browser.
4. Use **Mode** to toggle: 
   - `Pairs (JOIN)` for row-to-row comparison.
   - `Aggregated (LEFT JOINs)` for per-platform averages.
