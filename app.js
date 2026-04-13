const API_URL = 'api.php';
const EMPTY = 0, BLACK = 1, WHITE = 2;

let playerId = localStorage.getItem('playerId');
if (!playerId) {
    playerId = Math.random().toString(36).substr(2, 9);
    localStorage.setItem('playerId', playerId);
}

let currentRoomId = localStorage.getItem('roomId') || null;
let currentGameState = null;
let pollInterval = null;
let isMyTurn = false;
let myColor = 0; // 1 for black, 2 for white
let isRequesting = false;
let isBotMode = false;
let botDifficulty = 'easy';

// DOM
const screens = document.querySelectorAll('.screen');
const boardEl = document.getElementById('board');
const roomInfoEl = document.getElementById('room-info-display');
const cardBlack = document.getElementById('card-black');
const cardWhite = document.getElementById('card-white');
const scoreBlack = document.getElementById('score-black');
const scoreWhite = document.getElementById('score-white');

const boardOverlay = document.getElementById('board-overlay');
const modalTitle = document.getElementById('modal-title');
const modalDesc = document.getElementById('modal-desc');
const modalBtn = document.getElementById('modal-btn');

const globalOverlay = document.getElementById('global-overlay');
const globalModalTitle = document.getElementById('global-modal-title');
const globalModalDesc = document.getElementById('global-modal-desc');

const toastMsg = document.getElementById('toast-message');

const roomCodeBox = document.getElementById('room-code-box');
const roomCodeText = document.getElementById('room-code-text');

function copyRoomCode() {
    if (currentGameState && currentGameState.room_id) {
        navigator.clipboard.writeText(currentGameState.room_id).then(() => {
            showToast("Oda kodu kopyalandı!");
        });
    }
}

function showScreen(id) {
    screens.forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}

function showJoinMenu() {
    document.getElementById('join-section').style.display = 'block';
}

function showGlobalModal(title, desc) {
    globalModalTitle.innerText = title;
    globalModalDesc.innerText = desc;
    globalOverlay.style.display = 'flex';
}

function showToast(msg) {
    toastMsg.innerText = msg;
    toastMsg.classList.remove('toast-hidden');
    setTimeout(() => {
        toastMsg.classList.add('toast-hidden');
    }, 3000);
}

// Request Helper
async function apiCall(action, params = {}) {
    const url = new URL(API_URL, window.location.href);
    url.searchParams.append('action', action);
    url.searchParams.append('player_id', playerId);
    if (currentRoomId) url.searchParams.append('room_id', currentRoomId);
    
    for (const key in params) {
        url.searchParams.append(key, params[key]);
    }

    try {
        const res = await fetch(url.toString());
        const data = await res.json();
        if (data.error) {
            console.error(data.error);
            return null;
        }
        return data;
    } catch (e) {
        console.error(e);
        return null;
    }
}

async function apiPost(action, body, params = {}) {
    const url = new URL(API_URL, window.location.href);
    url.searchParams.append('action', action);
    for (const key in params) {
        url.searchParams.append(key, params[key]);
    }

    try {
        const res = await fetch(url.toString(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.error) {
            console.error(data.error);
            return null;
        }
        return data;
    } catch(e) {
        console.error(e);
        return null;
    }
}

// Actions
async function startGame(type, difficulty = 'easy') {
    isRequesting = true;
    
    if (type === 'bot') {
        isBotMode = true;
        botDifficulty = difficulty;
        currentRoomId = null; 
        
        let initialBoard = Array(8).fill(0).map(() => Array(8).fill(0));
        initialBoard[3][3] = 2;
        initialBoard[3][4] = 1;
        initialBoard[4][3] = 1;
        initialBoard[4][4] = 2;

        const fakeState = {
            room_id: "Bot Modu",
            type: "bot",
            status: "playing",
            board: initialBoard,
            turn: 1, // Black starts
            black_id: playerId,
            white_id: "bot",
            valid_moves: [[2,3], [3,2], [4,5], [5,4]] // Default valid moves for black in starting Reversi
        };
        
        isRequesting = false;
        enterGameLogic(fakeState);
        return;
    }

    const data = await apiCall('create_room', { type, difficulty });
    isRequesting = false;
    if (data) {
        currentRoomId = data.room_id;
        localStorage.setItem('roomId', currentRoomId);
        enterGameLogic(data);
    } else {
        showGlobalModal("Hata", "Oda kurulamadı.");
    }
}

async function joinRoom() {
    const roomId = document.getElementById('room-input').value.trim();
    if (!roomId) return;
    
    isRequesting = true;
    currentRoomId = roomId; 
    const data = await apiCall('join_room');
    isRequesting = false;
    if (data) {
        localStorage.setItem('roomId', currentRoomId);
        enterGameLogic(data);
    } else {
        currentRoomId = null;
        showGlobalModal("Hata", "Odaya katılınamadı veya oda dolu.");
    }
}

async function makeMove(r, c) {
    if (!isMyTurn || isRequesting) return;
    isRequesting = true;

    if (isBotMode) {
        const p1Data = await apiPost('stateless_move', {
            board: currentGameState.board,
            turn: currentGameState.turn
        }, { row: r, col: c });
        
        if (p1Data) {
            updateBoard(p1Data);
            if (p1Data.status === 'playing' && p1Data.turn === WHITE) {
                triggerBotLoops(p1Data);
            } else {
                isRequesting = false;
            }
        } else {
            isRequesting = false;
        }
    } else {
        const data = await apiCall('move', { row: r, col: c });
        isRequesting = false;
        if (data) {
            updateBoard(data);
        }
    }
}

async function triggerBotLoops(state) {
    if (state.status !== 'playing' || state.turn !== WHITE) {
        isRequesting = false;
        return;
    }
    
    setTimeout(async () => {
        isRequesting = true;
        const botData = await apiPost('stateless_bot_move', {
            board: state.board,
            difficulty: botDifficulty
        });
        
        if (botData) {
            updateBoard(botData);
            if (botData.status === 'playing' && botData.turn === WHITE) {
                triggerBotLoops(botData);
            } else {
                isRequesting = false;
            }
        } else {
            isRequesting = false;
        }
    }, 400); // 400ms bot delay for faster gameplay but maintaining visual order
}

async function pollState() {
    if (!currentRoomId || isRequesting) return;
    const data = await apiCall('get_state');
    if (data) {
        updateBoard(data);
    } else {
        leaveGame(true);
        showGlobalModal("Oda Bulunamadı", "Bu oyun süresi dolduğu için veya kapandığı için silinmiş.");
    }
}

function leaveGame(silent = false) {
    if (currentRoomId && !silent && !isBotMode) {
        apiCall('leave_room').catch(e => console.log(e));
    }
    currentRoomId = null;
    isBotMode = false;
    localStorage.removeItem('roomId');
    if (pollInterval) clearInterval(pollInterval);
    showScreen('menu-screen');
    boardOverlay.style.display = 'none';
}

function enterGameLogic(state) {
    showScreen('game-screen');
    createBoardCells();
    updateBoard(state);
    
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(pollState, 1000);
}

function createBoardCells() {
    boardEl.innerHTML = '';
    for (let r = 0; r < 8; r++) {
        for (let c = 0; c < 8; c++) {
            const cell = document.createElement('div');
            cell.className = 'cell';
            cell.dataset.r = r;
            cell.dataset.c = c;
            cell.onclick = () => makeMove(r, c);
            
            // The 3D piece wrapper
            const piece = document.createElement('div');
            piece.className = 'piece';
            
            // Faces
            const blackFace = document.createElement('div');
            blackFace.className = 'face face-black';
            piece.appendChild(blackFace);
            
            const whiteFace = document.createElement('div');
            whiteFace.className = 'face face-white';
            piece.appendChild(whiteFace);
            
            cell.appendChild(piece);
            boardEl.appendChild(cell);
        }
    }
}

function updateBoard(state) {
    const oldState = currentGameState;
    // merge to keep meta information
    currentGameState = { ...currentGameState, ...state };
    
    roomInfoEl.innerText = isBotMode ? "Mod: Bot" : `Oda: ${currentGameState.room_id}`;
    
    if (isBotMode) {
        myColor = BLACK; // you are always black in bot mode
    } else {
        if (playerId === currentGameState.black_id) myColor = BLACK;
        else if (playerId === currentGameState.white_id) myColor = WHITE;
        else myColor = 0; 
    }

    isMyTurn = (currentGameState.turn === myColor && currentGameState.status === 'playing');

    let bCount = 0, wCount = 0;

    for (let r = 0; r < 8; r++) {
        for (let c = 0; c < 8; c++) {
            const val = currentGameState.board[r][c];
            if (val === BLACK) bCount++;
            if (val === WHITE) wCount++;
            
            const cellIndex = r * 8 + c;
            const cell = boardEl.children[cellIndex];
            const piece = cell.children[0];
            
            cell.classList.remove('valid-move');

            if (val !== EMPTY) {
                // Add show class if not present
                piece.classList.add('show');
                // Set rotation
                if (val === BLACK) {
                    piece.classList.remove('flip');
                } else if (val === WHITE) {
                    piece.classList.add('flip');
                }
            } else {
                piece.classList.remove('show', 'flip');
            }
        }
    }

    scoreBlack.innerText = bCount;
    scoreWhite.innerText = wCount;

    // Show valid moves
    if (isMyTurn && currentGameState.valid_moves && currentGameState.status === 'playing') {
        currentGameState.valid_moves.forEach(m => {
            const ci = m[0] * 8 + m[1];
            boardEl.children[ci].classList.add('valid-move');
        });
    }

    // Status Board overlay & cards
    cardBlack.classList.remove('active');
    cardWhite.classList.remove('active');
    
    if (currentGameState.status === 'waiting') {
        boardOverlay.style.display = 'flex';
        modalTitle.innerText = "Rakip Bekleniyor...";
        modalDesc.innerText = "Arkadaşının girmesi gereken oda kodu:";
        roomCodeBox.style.display = 'flex';
        roomCodeText.innerText = currentGameState.room_id;
        modalBtn.style.display = 'none';
        cardBlack.classList.add('active'); // default
    } else if (currentGameState.status === 'playing') {
        boardOverlay.style.display = 'none';
        roomCodeBox.style.display = 'none';
        if (currentGameState.turn === BLACK) cardBlack.classList.add('active');
        else cardWhite.classList.add('active');
        
    } else if (currentGameState.status === 'finished') {
        boardOverlay.style.display = 'flex';
        roomCodeBox.style.display = 'none';
        modalBtn.style.display = 'block';
        
        let winText = "Berabere!";
        if (currentGameState.winner === BLACK) winText = "Siyah Kazandı!";
        else if (currentGameState.winner === WHITE) winText = "Beyaz Kazandı!";
        
        modalTitle.innerText = winText;
        modalDesc.innerText = `${bCount} - ${wCount}`;
        
        if(currentGameState.winner === BLACK) cardBlack.classList.add('active');
        else if(currentGameState.winner === WHITE) cardWhite.classList.add('active');
    }
}

// Auto init
async function init() {
    if (currentRoomId) {
        const data = await apiCall('get_state');
        if (data) {
            enterGameLogic(data);
        } else {
            leaveGame(true);
        }
    }
}
init();
