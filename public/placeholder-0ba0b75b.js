const e=(t=300,a=450,s="Sin Imagen")=>{const i=`
    <svg width="${t}" height="${a}" xmlns="http://www.w3.org/2000/svg">
      <rect width="100%" height="100%" fill="#1a1a1a"/>
      <text 
        x="50%" 
        y="50%" 
        dominant-baseline="middle" 
        text-anchor="middle" 
        font-family="Arial, sans-serif" 
        font-size="18" 
        fill="#666"
      >${s}</text>
    </svg>
  `;return`data:image/svg+xml;base64,${btoa(i)}`},l=e(300,450,"Imagen no disponible"),n=e(100,150,"Sin imagen");export{l as m,n as s};
