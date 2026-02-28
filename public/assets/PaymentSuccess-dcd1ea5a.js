import{s as _,C as A,r as b,p as E,j as D,b as x,o as p,d as g,e,t as c,k as C,g as h,f as P,w as S}from"./vendor-d71df2f6.js";import{_ as $}from"./index-ef630ed1.js";const I={async printThermalTicket(i){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;const n=this.generateThermalHTML(i);return s.document.write(n),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir:",s),!1}},async printMultipleTickets(i){try{const s=window.open("","","width=400,height=600");if(!s)return console.error("No se pudo abrir la ventana de impresión"),!1;let n=`
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <style>
            ${this.getThermalStyles()}
          </style>
        </head>
        <body>
      `;return i.forEach((l,a)=>{n+=this.generateThermalTicketContent(l),a<i.length-1&&(n+='<div style="page-break-after: always; margin-top: 20px;"></div>')}),n+="</body></html>",s.document.write(n),s.document.close(),s.onload=()=>{s.print(),setTimeout(()=>{s.close()},1e3)},!0}catch(s){return console.error("Error al imprimir múltiples tickets:",s),!1}},generateThermalHTML(i){const s=this.generateThermalTicketContent(i);return`
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <style>
          ${this.getThermalStyles()}
        </style>
      </head>
      <body>
        ${s}
      </body>
      </html>
    `},generateThermalTicketContent(i){const n=new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});return`
      <div class="thermal-ticket">
        <div class="thermal-header">
          <div class="cinema-name">CINEA</div>
          <div class="cinema-subtitle">Cines Independientes</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-section">
          <div class="thermal-label">PELÍCULA:</div>
          <div class="thermal-value bold">${this.truncateText(i.movieTitle||"N/A",32)}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-row">
            <div class="thermal-col">
              <div class="thermal-label">FECHA:</div>
              <div class="thermal-value">${i.screeningDate||"N/A"}</div>
            </div>
            <div class="thermal-col">
              <div class="thermal-label">HORA:</div>
              <div class="thermal-value">${i.screeningTime||"N/A"}</div>
            </div>
          </div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ASIENTO:</div>
          <div class="thermal-value bold">${i.seatNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">ENTRADA:</div>
          <div class="thermal-barcode">${i.ticketNumber||"N/A"}</div>
          <div class="thermal-code-small">${i.ticketNumber||"N/A"}</div>
        </div>

        <div class="thermal-section">
          <div class="thermal-label">PRECIO:</div>
          <div class="thermal-value bold">${i.price||"0.00"} €</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>

        <div class="thermal-footer">
          <div class="thermal-small">Impreso: ${n}</div>
          <div class="thermal-small">Presenta este código en la entrada</div>
          <div class="thermal-small">Válido solo para la función indicada</div>
        </div>

        <div class="thermal-divider">═══════════════════════════</div>
      </div>
    `},getThermalStyles(){return`
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        font-family: 'Courier New', monospace;
        width: 80mm;
        padding: 0;
        margin: 0;
        background: white;
      }

      @page {
        size: 80mm 200mm;
        margin: 0;
        padding: 0;
      }

      .thermal-ticket {
        width: 100%;
        padding: 2mm 2mm;
        text-align: center;
        font-size: 10pt;
        line-height: 1.3;
      }

      .thermal-header {
        margin-bottom: 5mm;
        padding-bottom: 3mm;
        border-bottom: 1px solid #000;
      }

      .cinema-name {
        font-size: 18pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 2mm;
      }

      .cinema-subtitle {
        font-size: 8pt;
        color: #333;
      }

      .thermal-divider {
        text-align: center;
        font-size: 9pt;
        margin: 3mm 0;
        letter-spacing: 1px;
      }

      .thermal-section {
        margin: 3mm 0;
        text-align: left;
        padding: 0 2mm;
      }

      .thermal-label {
        font-size: 8pt;
        font-weight: bold;
        color: #333;
        margin-bottom: 1mm;
      }

      .thermal-value {
        font-size: 10pt;
        word-break: break-word;
      }

      .thermal-value.bold {
        font-weight: bold;
        font-size: 11pt;
      }

      .thermal-row {
        display: flex;
        gap: 5mm;
      }

      .thermal-col {
        flex: 1;
      }

      .thermal-barcode {
        font-family: 'Code 128', 'Courier New', monospace;
        font-size: 20pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin: 2mm 0;
        word-break: break-all;
      }

      .thermal-code-small {
        font-size: 8pt;
        letter-spacing: 1px;
        word-break: break-all;
      }

      .thermal-footer {
        margin-top: 3mm;
        padding-top: 2mm;
        border-top: 1px solid #000;
      }

      .thermal-small {
        font-size: 8pt;
        color: #555;
        margin: 1mm 0;
      }

      @media print {
        body {
          margin: 0;
          padding: 0;
        }
        .thermal-ticket {
          page-break-after: always;
        }
      }
    `},truncateText(i,s){return i.length<=s?i:i.substring(0,s-3)+"..."},getTicketData(i){const s=localStorage.getItem("tickets");if(s)try{const l=JSON.parse(s).find(a=>a.ticketNumber===i);if(l)return l}catch(n){console.error("Error al leer tickets del localStorage:",n)}return{ticketNumber:i,movieTitle:"Película",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"N/A",price:"10.00"}}};const L={class:"payment-success-page"},z={class:"container"},R={class:"success-card"},O={key:0,class:"ticket-display"},U={class:"ticket-section"},V={class:"info-grid"},F={class:"info-item"},H={class:"info-value code"},j={class:"info-item"},B={class:"info-value"},M={class:"info-item"},q={class:"info-value"},J={class:"info-item"},W={class:"info-value"},Y={class:"info-item"},G={class:"info-value"},K={class:"info-item"},Q={class:"info-value"},X={class:"purchase-details"},Z={class:"detail-row"},ee={class:"code"},te={class:"detail-row"},se={class:"detail-row"},ae={class:"amount"},ie={class:"next-steps"},le={class:"action-buttons"},ne=["disabled"],oe={key:0},re={key:1},de={__name:"PaymentSuccess",setup(i){const s=_(),n=A(),l=b(""),a=b(null),v=b(!1),k=E(()=>new Date().toLocaleDateString("es-ES",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"}));D(()=>{if(l.value=n.query.ticket||n.params.ticket||"N/A",!l.value||l.value==="N/A"){s.push("/");return}y(),window.scrollTo(0,0)});const y=()=>{try{const o=localStorage.getItem("tickets");if(o){const d=JSON.parse(o).find(r=>r.ticketNumber===l.value);if(d){a.value=d;return}}a.value={ticketNumber:l.value,movieTitle:"Película Seleccionada",screeningDate:new Date().toLocaleDateString("es-ES"),screeningTime:"20:00",seatNumber:"A1",price:"10.00"}}catch(o){console.error("Error al cargar datos del ticket:",o),a.value={ticketNumber:l.value,movieTitle:"N/A",screeningDate:"N/A",screeningTime:"N/A",seatNumber:"N/A",price:"10.00"}}},T=async()=>{var o,t,d,r,m;v.value=!0;try{await I.printThermalTicket({ticketNumber:l.value,movieTitle:((o=a.value)==null?void 0:o.movieTitle)||"Película",screeningDate:((t=a.value)==null?void 0:t.screeningDate)||"N/A",screeningTime:((d=a.value)==null?void 0:d.screeningTime)||"N/A",seatNumber:((r=a.value)==null?void 0:r.seatNumber)||"N/A",price:((m=a.value)==null?void 0:m.price)||"10.00"})?console.log("Impresión iniciada correctamente"):alert("No se pudo completar la impresión. Verifica tu impresora térmica.")}catch(u){console.error("Error durante la impresión:",u),alert("Error al intentar imprimir. Intenta de nuevo.")}finally{v.value=!1}},w=()=>{var m,u,f,N;const o=`
    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
      <h1>CINEA - Entrada</h1>
      <p><strong>${((m=a.value)==null?void 0:m.movieTitle)||"Película"}</strong></p>
      <p>Número: ${l.value}</p>
      <p>Fecha: ${((u=a.value)==null?void 0:u.screeningDate)||"N/A"}</p>
      <p>Hora: ${((f=a.value)==null?void 0:f.screeningTime)||"N/A"}</p>
      <p>Asiento: ${((N=a.value)==null?void 0:N.seatNumber)||"N/A"}</p>
    </div>
  `,t=new Blob([o],{type:"text/html"}),d=window.URL.createObjectURL(t),r=document.createElement("a");r.href=d,r.download=`entrada_${l.value}.html`,r.click(),window.URL.revokeObjectURL(d)};return(o,t)=>{var r;const d=x("router-link");return p(),g("div",L,[e("div",z,[e("div",R,[t[19]||(t[19]=e("div",{class:"success-header"},[e("div",{class:"success-icon-large icon-success"}),e("h1",null,"¡Pago Exitoso!"),e("p",{class:"success-subtitle"},"Tu compra ha sido procesada correctamente")],-1)),a.value?(p(),g("div",O,[e("div",U,[t[6]||(t[6]=e("h3",null,"Información de la Entrada",-1)),e("div",V,[e("div",F,[t[0]||(t[0]=e("span",{class:"info-label"},"Número de Entrada:",-1)),e("span",H,c(a.value.ticketNumber),1)]),e("div",j,[t[1]||(t[1]=e("span",{class:"info-label"},"Película:",-1)),e("span",B,c(a.value.movieTitle),1)]),e("div",M,[t[2]||(t[2]=e("span",{class:"info-label"},"Fecha:",-1)),e("span",q,c(a.value.screeningDate),1)]),e("div",J,[t[3]||(t[3]=e("span",{class:"info-label"},"Hora:",-1)),e("span",W,c(a.value.screeningTime),1)]),e("div",Y,[t[4]||(t[4]=e("span",{class:"info-label"},"Asiento:",-1)),e("span",G,c(a.value.seatNumber),1)]),e("div",K,[t[5]||(t[5]=e("span",{class:"info-label"},"Precio:",-1)),e("span",Q,c(a.value.price)+" €",1)])])])])):C("",!0),e("div",X,[t[10]||(t[10]=e("h3",null,"Detalles de la Compra",-1)),e("div",Z,[t[7]||(t[7]=e("span",null,"Número de Entrada:",-1)),e("span",ee,c(l.value),1)]),e("div",te,[t[8]||(t[8]=e("span",null,"Fecha de Compra:",-1)),e("span",null,c(k.value),1)]),e("div",se,[t[9]||(t[9]=e("span",null,"Importe Pagado:",-1)),e("span",ae,c(((r=a.value)==null?void 0:r.price)||"10.00")+" €",1)]),t[11]||(t[11]=e("div",{class:"detail-row"},[e("span",null,"Estado:"),e("span",{class:"status-badge status-success"},"Completado")],-1))]),e("div",ie,[t[17]||(t[17]=e("h3",null,"Próximos Pasos",-1)),e("ol",null,[e("li",null,[t[12]||(t[12]=h("Recuerda tu número de entrada: ",-1)),e("strong",null,c(l.value),1)]),t[13]||(t[13]=e("li",null,"Recibirás un email de confirmación con tu comprobante",-1)),t[14]||(t[14]=e("li",null,"Puedes imprimir tu entrada desde el botón de abajo",-1)),t[15]||(t[15]=e("li",null,"Presenta tu entrada en la taquilla del cine",-1)),t[16]||(t[16]=e("li",null,"Llega 15 minutos antes de que comience la función",-1))])]),e("div",le,[e("button",{onClick:T,class:"btn btn-primary btn-large",disabled:v.value},[v.value?(p(),g("span",re,"Imprimiendo...")):(p(),g("span",oe,"Imprimir Entrada"))],8,ne),e("button",{onClick:w,class:"btn btn-secondary btn-large"}," Descargar Entrada "),P(d,{to:"/",class:"btn btn-tertiary btn-large"},{default:S(()=>[...t[18]||(t[18]=[h(" Volver al Inicio ",-1)])]),_:1})]),t[20]||(t[20]=e("div",{class:"contact-section"},[e("h4",null,"¿Necesitas ayuda?"),e("p",null,[h("Contacta con nosotros en "),e("strong",null,"info@cinea.es")]),e("p",null,[h("Teléfono: "),e("strong",null,"+34 91 123 4567")]),e("p",{class:"hours"},"Lun-Dom: 10:00 - 22:00")],-1))])]),t[21]||(t[21]=e("div",{id:"print-frame",style:{display:"none"}},null,-1))])}}},ue=$(de,[["__scopeId","data-v-8907864e"]]);export{ue as default};
