# 📘 Readme Generator

**Innoraft Readme Generator** is a command-line PHP utility that generates a Drupal-style `README.md` file for any Drupal module or PHP codebase using AI. It scans the provided module, extracts structured metadata, and produces a high-quality, standardized README automatically.

## 🚀 Features

- Generates a `README.md` file in the module folder
- Supports `.env`-based configuration for flexible local/remote AI services
- Can be executed from both the **project root** and **within module folders**

---

## 🧰 Requirements

- PHP 8.1 or higher
- Composer
- API key and endpoint for AI summarization (e.g., GroqCloud/OpenAI)

---

## 📦 Installation

Run this from the **root of your Drupal project**:

```bash
composer require innoraft/readme-generator --dev
```
---

## ⚙️ Setup

Copy the example environment file and update it with your actual credentials:

```bash
cp .env.example .env
> These settings are used to connect to the AI summarization service.

---

## ✅ Usage

### 📍 From Project Root

```bash
vendor/bin/readme-generator web/modules/contrib/MODULE_NAME
```

This command scans the specified module and writes `README.md` inside it.

---

## 📂 Output

After running the command, you'll get a `README.md` file with:

- Module name
- Description
- Key features and functionality
- Dependency info
- Usage instructions (if derivable)
- Auto-generated AI summary

---

## 🤖 Behind the Scenes

1. Codebase is scanned and structured data is extracted.
2. Data is sent to an AI service configured via `.env`.
3. AI returns a formatted README, which is saved in your module folder.

---

## 🛠 Development Notes

- This package does not include a `vendor/` directory — it relies on your project’s Composer autoloading.
- If you are using this inside a Drupal module or sub-directory, ensure paths are resolved correctly.
- The binary path is defined in the `composer.json` under `"bin": ["bin/readme-generator"]`.

---

## 📬 Contributing

Found a bug or want to enhance it? Feel free to open issues or submit PRs!

---

## 🧑‍💻 Authors

- [Arun Sahijpal](mailto:arunsahijpal111@gmail.com)
- [Kul Pratap Singh](mailto:kulpratap98@gmail.com)

---

## 📄 License

MIT License