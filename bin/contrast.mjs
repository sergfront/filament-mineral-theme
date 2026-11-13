import { readFile, readdir } from 'node:fs/promises';
const luminance = h => { const c = h.match(/[a-f0-9]{2}/gi).map(v => parseInt(v,16)/255).map(v => v <= .04045 ? v/12.92 : ((v+.055)/1.055)**2.4); return c[0]*.2126+c[1]*.7152+c[2]*.0722; };
let count = 0;
for (const file of await readdir('resources/css/palettes')) {
 const css = await readFile(`resources/css/palettes/${file}`, 'utf8');
 const blocks = [...css.matchAll(/\{([^}]+)\}/g)];
 if (blocks.length !== 2) throw Error(`${file}: expected light and dark`);
 for (const [i, block] of blocks.entries()) {
  const vars = Object.fromEntries([...block[1].matchAll(/--mineral-([\w-]+):\s*(#[a-f0-9]{6})/gi)].map(m => [m[1],m[2]]));
  for (const [a,b] of [['text','background'],['text','surface'],['muted','background'],['muted','surface'],['on-accent','accent'],['accent','background'],['accent','surface']]) {
   if (!vars[a] || !vars[b]) throw Error(`${file}: missing ${a}/${b}`);
   const l=[luminance(vars[a]),luminance(vars[b])].sort((x,y)=>y-x), ratio=(l[0]+.05)/(l[1]+.05);
   if(ratio < 4.5) throw Error(`${file} ${i?'dark':'light'} ${a}/${b}: ${ratio.toFixed(2)}`);
   count++;
  }
 }
}
console.log(`${count} text contrast checks passed (WCAG AA 4.5:1).`);
