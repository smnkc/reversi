<?php
header('Content-Type: application/json');

require_once 'core.php';

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// --- Background Cleanup ---
$files = glob($dataDir . '/*.json');
$now = time();
foreach ($files as $file) {
    if (is_file($file)) {
        $content = file_get_contents($file);
        if ($content) {
            $state = json_decode($content, true);
            if ($state) {
                // Rakip gelmediyse 1 dakika sonra sil
                if ($state['status'] === 'waiting' && ($now - $state['created_at']) > 60) {
                    unlink($file);
                }
                // Oyun bittiyse 1 dakika sonra sil
                if ($state['status'] === 'finished' && ($now - $state['last_activity']) > 60) {
                    unlink($file);
                }
                // Terk edilen oyunları 1 saat (3600 saniye) sonra sil
                if ($state['status'] === 'playing' && isset($state['last_activity']) && ($now - $state['last_activity']) > 3600) {
                    unlink($file);
                }
            }
        }
    }
}
// --- // ---

function sendError($msg) {
    echo json_encode(['error' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';
$roomId = $_GET['room_id'] ?? '';
$playerId = $_GET['player_id'] ?? '';

if (!$action) sendError("No action specified");

function readState($roomId) {
    global $dataDir;
    $file = $dataDir . '/' . $roomId . '.json';
    if (!file_exists($file)) return null;
    
    for ($i = 0; $i < 3; $i++) {
        $content = file_get_contents($file);
        if ($content) {
            $state = json_decode($content, true);
            if (is_array($state)) return $state;
        }
        usleep(50000);
    }
    return null;
}

function writeState($roomId, $state) {
    global $dataDir;
    $state['last_activity'] = time();
    $file = $dataDir . '/' . $roomId . '.json';
    file_put_contents($file, json_encode($state), LOCK_EX);
}

function outputState($state) {
    if (!$state) return;
    $state['valid_moves'] = Reversi::getValidMoves($state['board'], $state['turn']);
    echo json_encode($state);
    exit;
}

if ($action === 'create_room') {
    if (!$playerId) sendError("player_id required");
    $type = $_GET['type'] ?? 'friend'; // friend veya bot
    $difficulty = $_GET['difficulty'] ?? 'easy';

    do {
        $roomId = (string) mt_rand(1000, 9999);
        $file = $dataDir . '/' . $roomId . '.json';
    } while (file_exists($file));

    $state = [
        'room_id' => $roomId,
        'type' => $type,
        'bot_difficulty' => $difficulty,
        'status' => ($type === 'bot') ? 'playing' : 'waiting',
        'board' => Reversi::getInitialBoard(),
        'turn' => Reversi::BLACK,
        'black_id' => $playerId,
        'white_id' => ($type === 'bot') ? 'bot' : null,
        'created_at' => time(),
        'last_activity' => time(),
        'winner' => 0
    ];

    writeState($roomId, $state);
    outputState($state);
}

if ($action === 'join_room') {
    if (!$roomId || !$playerId) sendError("room_id and player_id required");
    $state = readState($roomId);
    if (!$state) sendError("Room not found");
    if ($state['status'] !== 'waiting') sendError("Room is not available");

    $state['white_id'] = $playerId;
    $state['status'] = 'playing';
    writeState($roomId, $state);

    outputState($state);
}

if ($action === 'get_state') {
    if (!$roomId) sendError("room_id required");
    $state = readState($roomId);
    if (!$state) sendError("Room not found");
    outputState($state);
}

if ($action === 'move') {
    if (!$roomId || !$playerId) sendError("room_id and player_id required");
    $row = isset($_GET['row']) ? (int)$_GET['row'] : -1;
    $col = isset($_GET['col']) ? (int)$_GET['col'] : -1;

    $state = readState($roomId);
    if (!$state) sendError("Room not found");
    if ($state['status'] !== 'playing') sendError("Game is not active");

    $currentPlayer = $state['turn'];
    $allowedPlayerId = ($currentPlayer === Reversi::BLACK) ? $state['black_id'] : $state['white_id'];

    if ($playerId !== $allowedPlayerId) {
        sendError("Not your turn or you are not in this game");
    }

    // Check valid move
    if (!Reversi::isValidMove($state['board'], $currentPlayer, $row, $col)) {
        sendError("Invalid move");
    }

    // Apply move
    $state['board'] = Reversi::applyMove($state['board'], $currentPlayer, $row, $col);
    
    // Switch turn logic setup
    function switchTurn(&$state) {
        $next = Reversi::getOpponent($state['turn']);
        if (count(Reversi::getValidMoves($state['board'], $next)) > 0) {
            $state['turn'] = $next;
            return true;
        } else if (count(Reversi::getValidMoves($state['board'], $state['turn'])) > 0) {
            // Next player has no moves, keep current turn
            return true;
        }
        
        // No moves for anyone
        $state['status'] = 'finished';
        $counts = Reversi::getCounts($state['board']);
        if ($counts['black'] > $counts['white']) $state['winner'] = Reversi::BLACK;
        else if ($counts['white'] > $counts['black']) $state['winner'] = Reversi::WHITE;
        else $state['winner'] = 3; // Draw
        return false;
    }

    $gameContinues = switchTurn($state);

    // Bot move handling 
    if ($gameContinues && $state['type'] === 'bot' && $state['turn'] === Reversi::WHITE) {
        // Bot plays for WHITE
        $botMove = null;
        if ($state['bot_difficulty'] === 'easy') {
            $botMove = Bot::moveEasy($state['board'], Reversi::WHITE);
        } else if ($state['bot_difficulty'] === 'medium') {
            $botMove = Bot::moveMedium($state['board'], Reversi::WHITE);
        } else if ($state['bot_difficulty'] === 'hard') {
            $botMove = Bot::moveHard($state['board'], Reversi::WHITE);
        }

        if ($botMove) {
            $state['board'] = Reversi::applyMove($state['board'], Reversi::WHITE, $botMove[0], $botMove[1]);
            switchTurn($state); // switch back to player, or check endgame
        } else {
            // bot had no move, switch turn back to player
            switchTurn($state); 
        }
    }

    writeState($roomId, $state);
    outputState($state);
}

if ($action === 'leave_room') {
    if (!$roomId || !$playerId) sendError("room_id and player_id required");
    $state = readState($roomId);
    if ($state && $state['status'] === 'waiting' && $state['black_id'] === $playerId) {
        $file = $dataDir . '/' . $roomId . '.json';
        if (file_exists($file)) unlink($file);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'stateless_move') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendError("Invalid JSON");
    $board = $input['board'];
    $turn = $input['turn'];
    $row = isset($_GET['row']) ? (int)$_GET['row'] : -1;
    $col = isset($_GET['col']) ? (int)$_GET['col'] : -1;
    
    if ($row >= 0 && $col >= 0) {
        if (!Reversi::isValidMove($board, $turn, $row, $col)) sendError("Invalid move");
        $board = Reversi::applyMove($board, $turn, $row, $col);
    }
    
    $status = 'playing';
    $winner = 0;
    $next = Reversi::getOpponent($turn);
    if (count(Reversi::getValidMoves($board, $next)) > 0) {
        $turn = $next;
    } else if (count(Reversi::getValidMoves($board, $turn)) == 0) {
        $status = 'finished';
        $counts = Reversi::getCounts($board);
        if ($counts['black'] > $counts['white']) $winner = 1;
        else if ($counts['white'] > $counts['black']) $winner = 2;
        else $winner = 3;
    }
    
    echo json_encode(['board' => $board, 'turn' => $turn, 'status' => $status, 'winner' => $winner, 'valid_moves' => Reversi::getValidMoves($board, $turn)]);
    exit;
}

if ($action === 'stateless_bot_move') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) sendError("Invalid JSON");
    $board = $input['board'];
    $difficulty = $input['difficulty'] ?? 'easy';
    
    $botMove = null;
    if ($difficulty === 'easy') $botMove = Bot::moveEasy($board, Reversi::WHITE);
    else if ($difficulty === 'medium') $botMove = Bot::moveMedium($board, Reversi::WHITE);
    else if ($difficulty === 'hard') $botMove = Bot::moveHard($board, Reversi::WHITE);

    $turn = Reversi::WHITE;
    if ($botMove) {
        $board = Reversi::applyMove($board, Reversi::WHITE, $botMove[0], $botMove[1]);
    }
    
    $status = 'playing';
    $winner = 0;
    $next = Reversi::BLACK;
    if (count(Reversi::getValidMoves($board, $next)) > 0) {
        $turn = $next;
    } else if (count(Reversi::getValidMoves($board, $turn)) > 0) {
        $turn = Reversi::WHITE;
    } else {
        $status = 'finished';
        $counts = Reversi::getCounts($board);
        if ($counts['black'] > $counts['white']) $winner = 1;
        else if ($counts['white'] > $counts['black']) $winner = 2;
        else $winner = 3;
    }
    
    echo json_encode(['board' => $board, 'turn' => $turn, 'status' => $status, 'winner' => $winner, 'valid_moves' => Reversi::getValidMoves($board, $turn)]);
    exit;
}

sendError("Invalid action");
