" headlines
syn match Exception /^=.*/

" links
syn match Comment /\[\[[^\]]*\]\]/

" bold
syn match Identifier /\*\*[^*]\{-}\*\*/

" code blocks
syn match Identifier /^{{{\_.\{-}\n}}}/
syn match Identifier /{{{.\{-}}}}/
