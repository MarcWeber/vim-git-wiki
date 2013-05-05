augroup BufCreate,BufNewFile
  autocmd BufRead,BufNewFile * if expand('%:p') =~ 'vim-online-wiki-source' | set ft=vim_online_wiki | endif
augroup end
