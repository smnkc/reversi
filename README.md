# Sleek Reversi PRO

A modern, responsive, and feature-rich Reversi (Othello) game built with HTML5, CSS3, JavaScript, and PHP.

![Sleek Reversi PRO](icon.png)

## 🌟 Features

- **Modern UI**: Dark mode aesthetic with glassmorphism and smooth animations.
- **Mobile First**: Fully responsive design optimized for mobile and desktop.
- **Single Player vs AI**: Play against a bot with three difficulty levels:
  - **Easy**: Random moves.
  - **Medium**: Strategic corner and edge prioritization.
  - **Hard**: MiniMax algorithm with alpha-beta pruning (simulated strategic play).
- **Online Multiplayer**: Create rooms and play with friends in real-time.
- **JSON Powered**: Real-time gameplay state managed via PHP and JSON files.
- **Auto-Cleanup**: Game rooms and data are automatically deleted after completion or inactivity.
- **PWA Ready**: Offline support and installable as an app.

## 🚀 Tech Stack

- **Frontend**: Vanilla JavaScript, HTML5, CSS3.
- **Backend**: PHP 8.x.
- **Storage**: JSON files (No database required).
- **Service Worker**: For PWA functionality.

## 🛠️ Installation

1. Clone the repository to your local server (e.g., XAMPP, WAMP, or any PHP-enabled host).
   ```bash
   git clone https://github.com/yourusername/reversi-pro.git
   ```
2. Ensure the `data/` directory has write permissions.
   ```bash
   chmod 777 data
   ```
3. Open the directory in your browser (e.g., `http://localhost/reversi-pro`).

## 📁 Directory Structure

- `index.html`: Main game interface.
- `style.css`: Modern styling and animations.
- `app.js`: Core game logic and frontend reactivity.
- `api.php`: Backend API for room management and moves.
- `core.php`: Server-side game engine logic.
- `data/`: Temporary storage for active game rooms.
- `sw.js`: Service worker for PWA.

## 🛡️ License

MIT License. Feel free to use and improve!
