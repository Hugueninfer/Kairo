<?php

// Mensagens de validação em português. Novas regras usadas no projeto devem ganhar sua tradução aqui.

return [
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'date_format' => 'O campo :attribute deve ser uma data no formato :format.',
    'decimal' => 'O campo :attribute deve ter no máximo :decimal casas decimais.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'enum' => 'O valor de :attribute é inválido.',
    'exists' => 'O valor de :attribute não foi encontrado.',
    'in' => 'O valor de :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
        'file' => 'O arquivo :attribute não pode ter mais de :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O arquivo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'O campo :attribute deve ter pelo menos uma letra.',
        'mixed' => 'O campo :attribute deve ter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'O campo :attribute deve ter pelo menos um número.',
        'symbols' => 'O campo :attribute deve ter pelo menos um símbolo.',
        'uncompromised' => 'Esta :attribute apareceu em um vazamento de dados. Escolha outra.',
    ],
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório.',
    'size' => [
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'remember' => 'lembrar de mim',
        'token' => 'token',
        'client_id' => 'cliente',
        'title' => 'título',
        'valid_until' => 'validade',
        'items' => 'itens',
        'discount' => 'desconto',
        'tax_rate' => 'impostos',
    ],
];
