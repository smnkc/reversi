<?php

class Reversi {
    const EMPTY = 0;
    const BLACK = 1;
    const WHITE = 2;

    public static function getInitialBoard() {
        $board = array_fill(0, 8, array_fill(0, 8, self::EMPTY));
        $board[3][3] = self::WHITE;
        $board[3][4] = self::BLACK;
        $board[4][3] = self::BLACK;
        $board[4][4] = self::WHITE;
        return $board;
    }

    public static function getOpponent($player) {
        return 3 - $player;
    }

    public static function getFlippedPieces($board, $player, $row, $col) {
        $flipped = [];
        if ($board[$row][$col] !== self::EMPTY) {
            return $flipped;
        }

        $opponent = self::getOpponent($player);
        $directions = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1]
        ];

        foreach ($directions as $dir) {
            $r = $row + $dir[0];
            $c = $col + $dir[1];
            $tempFlipped = [];

            while ($r >= 0 && $r < 8 && $c >= 0 && $c < 8 && $board[$r][$c] === $opponent) {
                $tempFlipped[] = [$r, $c];
                $r += $dir[0];
                $c += $dir[1];
            }

            if ($r >= 0 && $r < 8 && $c >= 0 && $c < 8 && $board[$r][$c] === $player) {
                $flipped = array_merge($flipped, $tempFlipped);
            }
        }

        return $flipped;
    }

    public static function isValidMove($board, $player, $row, $col) {
        return count(self::getFlippedPieces($board, $player, $row, $col)) > 0;
    }

    public static function getValidMoves($board, $player) {
        $moves = [];
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if (self::isValidMove($board, $player, $r, $c)) {
                    $moves[] = [$r, $c];
                }
            }
        }
        return $moves;
    }

    public static function applyMove($board, $player, $row, $col) {
        $flipped = self::getFlippedPieces($board, $player, $row, $col);
        if (count($flipped) === 0) return $board;

        $newBoard = $board;
        $newBoard[$row][$col] = $player;
        foreach ($flipped as $f) {
            $newBoard[$f[0]][$f[1]] = $player;
        }
        return $newBoard;
    }

    public static function isGameOver($board) {
        return count(self::getValidMoves($board, self::BLACK)) === 0 &&
               count(self::getValidMoves($board, self::WHITE)) === 0;
    }

    public static function getCounts($board) {
        $black = 0;
        $white = 0;
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($board[$r][$c] === self::BLACK) $black++;
                if ($board[$r][$c] === self::WHITE) $white++;
            }
        }
        return ['black' => $black, 'white' => $white];
    }
}

class Bot {
    public static function moveEasy($board, $player) {
        $moves = Reversi::getValidMoves($board, $player);
        if (empty($moves)) return null;
        return $moves[array_rand($moves)];
    }

    public static function moveMedium($board, $player) {
        $moves = Reversi::getValidMoves($board, $player);
        if (empty($moves)) return null;

        $bestMove = $moves[0];
        $maxFlips = -1;

        foreach ($moves as $move) {
            $flips = count(Reversi::getFlippedPieces($board, $player, $move[0], $move[1]));
            if ($flips > $maxFlips) {
                $maxFlips = $flips;
                $bestMove = $move;
            }
        }

        return $bestMove;
    }

    private static $weights = [
        [100, -20,  10,   5,   5,  10, -20, 100],
        [-20, -50,  -2,  -2,  -2,  -2, -50, -20],
        [ 10,  -2,  -1,  -1,  -1,  -1,  -2,  10],
        [  5,  -2,  -1,  -1,  -1,  -1,  -2,   5],
        [  5,  -2,  -1,  -1,  -1,  -1,  -2,   5],
        [ 10,  -2,  -1,  -1,  -1,  -1,  -2,  10],
        [-20, -50,  -2,  -2,  -2,  -2, -50, -20],
        [100, -20,  10,   5,   5,  10, -20, 100]
    ];

    public static function moveHard($board, $player) {
        $moves = Reversi::getValidMoves($board, $player);
        if (empty($moves)) return null;

        $bestScore = -999999;
        $bestMove = $moves[0];

        foreach ($moves as $move) {
            $newBoard = Reversi::applyMove($board, $player, $move[0], $move[1]);
            $score = self::minimax($newBoard, 4, false, -999999, 999999, Reversi::getOpponent($player), $player);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMove = $move;
            }
        }
        return $bestMove;
    }

    private static function minimax($board, $depth, $isMaximizing, $alpha, $beta, $currentPlayer, $maximizingPlayer) {
        if ($depth == 0 || Reversi::isGameOver($board)) {
            return self::evaluateBoard($board, $maximizingPlayer);
        }

        $moves = Reversi::getValidMoves($board, $currentPlayer);
        
        // Pass validation
        if (empty($moves)) {
            return self::minimax($board, $depth - 1, !$isMaximizing, $alpha, $beta, Reversi::getOpponent($currentPlayer), $maximizingPlayer);
        }

        if ($isMaximizing) {
            $maxEval = -999999;
            foreach ($moves as $move) {
                $newBoard = Reversi::applyMove($board, $currentPlayer, $move[0], $move[1]);
                $eval = self::minimax($newBoard, $depth - 1, false, $alpha, $beta, Reversi::getOpponent($currentPlayer), $maximizingPlayer);
                $maxEval = max($maxEval, $eval);
                $alpha = max($alpha, $eval);
                if ($beta <= $alpha) break;
            }
            return $maxEval;
        } else {
            $minEval = 999999;
            foreach ($moves as $move) {
                $newBoard = Reversi::applyMove($board, $currentPlayer, $move[0], $move[1]);
                $eval = self::minimax($newBoard, $depth - 1, true, $alpha, $beta, Reversi::getOpponent($currentPlayer), $maximizingPlayer);
                $minEval = min($minEval, $eval);
                $beta = min($beta, $eval);
                if ($beta <= $alpha) break;
            }
            return $minEval;
        }
    }

    private static function evaluateBoard($board, $player) {
        $score = 0;
        $opponent = Reversi::getOpponent($player);
        for ($r = 0; $r < 8; $r++) {
            for ($c = 0; $c < 8; $c++) {
                if ($board[$r][$c] === $player) {
                    $score += self::$weights[$r][$c];
                } elseif ($board[$r][$c] === $opponent) {
                    $score -= self::$weights[$r][$c];
                }
            }
        }
        return $score;
    }
}
