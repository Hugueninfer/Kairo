<?php

use App\Broadcasting\BoardChannel;
use Illuminate\Support\Facades\Broadcast;

// Canal de presença de cada board: quem está vendo, cursores e alterações ao vivo.
// Só entra quem é da empresa dona do board e pode ver boards.
Broadcast::channel('board.{board}', BoardChannel::class);
