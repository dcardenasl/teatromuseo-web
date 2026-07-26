import fs from 'fs';
import postcss from 'postcss';
import tailwindcss from '@tailwindcss/postcss';
import autoprefixer from 'autoprefixer';

const input = fs.readFileSync('./public/assets/css/app.css', 'utf8');

postcss([tailwindcss(), autoprefixer])
  .process(input, { from: './public/assets/css/app.css' })
  .then(result => {
    fs.writeFileSync('./public/assets/css/compiled.css', result.css);
    console.log('✅ CSS compiled successfully!');
  })
  .catch(err => {
    console.error('❌ CSS compilation failed:', err);
    process.exit(1);
  });
