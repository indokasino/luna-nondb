# LUNA AI Chatbot System

Sistem chatbot AI untuk layanan customer service di Indokasino. Luna adalah asisten virtual yang membantu menjawab berbagai pertanyaan pengguna dan menyimpan pengetahuan secara otomatis untuk referensi masa depan.

## Struktur Direktori

```
.
├── knowledge-article.php       # Halaman web untuk menampilkan knowledge base
├── prompt/
│   └── prompt-luna.txt        # File prompt karakter Luna
├── config/
│   ├── config.php             # Konfigurasi umum sistem
│   └── openai_config.php      # Konfigurasi akses API OpenAI
├── database/
│   ├── luna.sqlite            # File database SQLite
│   └── migrations/
│       └── create_tables.sql  # Script SQL untuk membuat tabel
├── includes/
│   ├── ai_engine.php          # Kode untuk integrasi dengan OpenAI 
│   ├── database.php           # Class untuk akses database
│   ├── response_formatter.php # Format respons untuk chatbot
│   ├── knowledge_cleanup.php  # Script untuk membersihkan knowledge base
│   └── auto_import.php        # Script untuk import data dari conversations
└── api/
    ├── conversation_log.php   # API untuk mengambil log percakapan
    └── webhook.php            # API webhook untuk chatbot
```

## Fitur Utama

1. **Webhook Chatbot**: Menerima dan merespons pesan pengguna
2. **Penyimpanan Otomatis**: Menyimpan percakapan di tabel `conversations`
3. **Knowledge Base**: Menyimpan pertanyaan dan jawaban berkualitas di tabel `knowledge_base`
4. **Tampilan Knowledge Base**: Halaman web untuk melihat knowledge base
5. **Sistem Feedback**: Menyimpan skor dan catatan feedback untuk perbaikan

## Setup Awal

1. Pastikan PHP 7.4+ dan SQLite3 sudah terinstal
2. Clone repositori ke server
3. Buat database (jika belum ada):
   ```bash
   cat database/migrations/create_tables.sql | sqlite3 database/luna.sqlite
   ```
4. Pastikan permission file database sudah benar:
   ```bash
   chmod 664 database/luna.sqlite
   chown www-data:www-data database/luna.sqlite
   ```
5. Sesuaikan konfigurasi di `config/config.php` dan `config/openai_config.php`

## Pemeliharaan Database

Untuk memastikan knowledge base tetap bersih dan optimal, jalankan script berikut secara berkala:

1. Auto import dari conversations ke knowledge base:
   ```bash
   php includes/auto_import.php
   ```

2. Pembersihan knowledge base dari duplikasi:
   ```bash
   php includes/knowledge_cleanup.php
   ```

Sebaiknya kedua script di atas dijalankan menggunakan cron job:
```
# Jalankan auto_import setiap hari jam 2 pagi
0 2 * * * php /path/to/includes/auto_import.php >> /path/to/logs/auto_import.log 2>&1

# Jalankan knowledge_cleanup setiap hari jam 3 pagi
0 3 * * * php /path/to/includes/knowledge_cleanup.php >> /path/to/logs/cleanup.log 2>&1
```

## Alur Kerja Sistem

1. Pengguna mengirim pesan melalui chatbot
2. Webhook (`api/webhook.php`) menerima pesan
3. `ai_engine.php` memproses pesan menggunakan OpenAI API
4. Sistem menyimpan percakapan di tabel `conversations`
5. Jika pertanyaan cukup berkualitas, sistem menyimpannya juga di tabel `knowledge_base`
6. Pengguna dapat melihat knowledge base melalui `knowledge-article.php`

## Pengembangan Lebih Lanjut

1. Tambahkan fitur pencarian di halaman knowledge base
2. Implementasikan kategorisasi otomatis untuk pertanyaan
3. Buat dashboard admin untuk mengelola knowledge base

## Kontributor

- Developer: [Nama Anda]
- UI Designer: [Nama Designer]

## Lisensi

Hak Cipta © 2025 Indokasino. Semua hak dilindungi.