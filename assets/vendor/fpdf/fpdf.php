<?php
/*******************************************************************************
* FPDF - Minimal Version for SpotLight Studio                                 *
*******************************************************************************/

define('FPDF_VERSION','1.85');

class FPDF {
protected $page;
protected $n;
protected $offsets;
protected $buffer;
protected $pages;
protected $state;
protected $compress;
protected $k;
protected $DefOrientation;
protected $CurOrientation;
protected $StdPageSizes;
protected $DefPageSize;
protected $CurPageSize;
protected $wPt, $hPt;
protected $w, $h;
protected $lMargin;
protected $tMargin;
protected $rMargin;
protected $bMargin;
protected $cMargin;
protected $x, $y;
protected $lasth;
protected $LineWidth;
protected $fontpath;
protected $CoreFonts;
protected $fonts;
protected $FontFiles;
protected $diffs;
protected $FontFamily;
protected $FontStyle;
protected $underline;
protected $CurrentFont;
protected $FontSizePt;
protected $FontSize;
protected $DrawColor;
protected $FillColor;
protected $TextColor;
protected $ColorFlag;
protected $ws;
protected $images;
protected $PageLinks;
protected $links;
protected $AutoPageBreak;
protected $PageBreakTrigger;
protected $InHeader;
protected $InFooter;
protected $AliasNbPages;
protected $ZoomMode;
protected $LayoutMode;
protected $metadata;
protected $PDFVersion;

function __construct($orientation='P', $unit='mm', $size='A4') {
    $this->state = 0;
    $this->page = 0;
    $this->n = 2;
    $this->buffer = '';
    $this->pages = array();
    $this->fonts = array();
    $this->FontFiles = array();
    $this->diffs = array();
    $this->images = array();
    $this->links = array();
    $this->InHeader = false;
    $this->InFooter = false;
    $this->lasth = 0;
    $this->FontFamily = '';
    $this->FontStyle = '';
    $this->FontSizePt = 12;
    $this->underline = false;
    $this->DrawColor = '0 G';
    $this->FillColor = '0 g';
    $this->TextColor = '0 g';
    $this->ColorFlag = false;
    $this->ws = 0;
    $this->CoreFonts = array('courier'=>'Courier', 'courierB'=>'Courier-Bold', 'courierI'=>'Courier-Oblique', 'courierBI'=>'Courier-BoldOblique',
        'helvetica'=>'Helvetica', 'helveticaB'=>'Helvetica-Bold', 'helveticaI'=>'Helvetica-Oblique', 'helveticaBI'=>'Helvetica-BoldOblique',
        'times'=>'Times-Roman', 'timesB'=>'Times-Bold', 'timesI'=>'Times-Italic', 'timesBI'=>'Times-BoldItalic',
        'symbol'=>'Symbol', 'zapfdingbats'=>'ZapfDingbats');
    if($unit=='pt') $this->k = 1;
    elseif($unit=='mm') $this->k = 72/25.4;
    elseif($unit=='cm') $this->k = 72/2.54;
    elseif($unit=='in') $this->k = 72;
    $this->StdPageSizes = array('a3'=>array(841.89,1190.55), 'a4'=>array(595.28,841.89), 'a5'=>array(420.94,595.28),
        'letter'=>array(612,792), 'legal'=>array(612,1008));
    $size = $this->_getpagesize($size);
    $this->DefPageSize = $size;
    $this->CurPageSize = $size;
    $orientation = strtolower($orientation);
    if($orientation=='p' || $orientation=='portrait') {
        $this->DefOrientation = 'P';
        $this->w = $size[0];
        $this->h = $size[1];
    } elseif($orientation=='l' || $orientation=='landscape') {
        $this->DefOrientation = 'L';
        $this->w = $size[1];
        $this->h = $size[0];
    }
    $this->CurOrientation = $this->DefOrientation;
    $this->wPt = $this->w*$this->k;
    $this->hPt = $this->h*$this->k;
    $margin = 28.35/$this->k;
    $this->SetMargins($margin,$margin);
    $this->cMargin = $margin/10;
    $this->LineWidth = .567/$this->k;
    $this->SetAutoPageBreak(true,2*$margin);
    $this->SetDisplayMode('default');
    $this->SetCompression(true);
    $this->PDFVersion = '1.3';
}

function SetMargins($left, $top, $right=null) {
    $this->lMargin = $left;
    $this->tMargin = $top;
    if($right===null) $right = $left;
    $this->rMargin = $right;
}

function SetAutoPageBreak($auto, $margin=0) {
    $this->AutoPageBreak = $auto;
    $this->bMargin = $margin;
    $this->PageBreakTrigger = $this->h-$margin;
}

function SetDisplayMode($zoom, $layout='default') {
    $this->ZoomMode = $zoom;
    $this->LayoutMode = $layout;
}

function SetCompression($compress) {
    $this->compress = $compress;
}

function SetTitle($title, $isUTF8=false) {
    $this->metadata['Title'] = $isUTF8 ? $title : utf8_encode($title);
}

function SetAuthor($author, $isUTF8=false) {
    $this->metadata['Author'] = $isUTF8 ? $author : utf8_encode($author);
}

function AliasNbPages($alias='{nb}') {
    $this->AliasNbPages = $alias;
}

function Error($msg) {
    throw new Exception('FPDF error: '.$msg);
}

function Close() {
    if($this->state==3) return;
    if($this->page==0) $this->AddPage();
    $this->InFooter = true;
    $this->Footer();
    $this->InFooter = false;
    $this->_endpage();
    $this->_enddoc();
}

function AddPage($orientation='', $size='', $rotation=0) {
    if($this->state==3) $this->Error('The document is closed');
    $family = $this->FontFamily;
    $style = $this->FontStyle.($this->underline ? 'U' : '');
    $fontsize = $this->FontSizePt;
    $lw = $this->LineWidth;
    $dc = $this->DrawColor;
    $fc = $this->FillColor;
    $tc = $this->TextColor;
    $cf = $this->ColorFlag;
    if($this->page>0) {
        $this->InFooter = true;
        $this->Footer();
        $this->InFooter = false;
        $this->_endpage();
    }
    $this->_beginpage($orientation,$size,$rotation);
    $this->_out('2 J');
    $this->LineWidth = $lw;
    $this->_out(sprintf('%.2F w',$lw*$this->k));
    if($family) $this->SetFont($family,$style,$fontsize);
    $this->DrawColor = $dc;
    if($dc!='0 G') $this->_out($dc);
    $this->FillColor = $fc;
    if($fc!='0 g') $this->_out($fc);
    $this->TextColor = $tc;
    $this->ColorFlag = $cf;
    $this->InHeader = true;
    $this->Header();
    $this->InHeader = false;
    if($this->y>$this->PageBreakTrigger) $this->AddPage($this->CurOrientation,'',0);
}

function Header() {}
function Footer() {}

function PageNo() {
    return $this->page;
}

function SetDrawColor($r, $g=null, $b=null) {
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->DrawColor = sprintf('%.3F G',$r/255);
    else
        $this->DrawColor = sprintf('%.3F %.3F %.3F RG',$r/255,$g/255,$b/255);
    if($this->page>0) $this->_out($this->DrawColor);
}

function SetFillColor($r, $g=null, $b=null) {
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->FillColor = sprintf('%.3F g',$r/255);
    else
        $this->FillColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
    if($this->page>0) $this->_out($this->FillColor);
}

function SetTextColor($r, $g=null, $b=null) {
    if(($r==0 && $g==0 && $b==0) || $g===null)
        $this->TextColor = sprintf('%.3F g',$r/255);
    else
        $this->TextColor = sprintf('%.3F %.3F %.3F rg',$r/255,$g/255,$b/255);
    $this->ColorFlag = ($this->FillColor!=$this->TextColor);
}

function GetStringWidth($s) {
    $s = (string)$s;
    $cw = $this->CurrentFont['cw'];
    $w = 0;
    $l = strlen($s);
    for($i=0;$i<$l;$i++) $w += $cw[$s[$i]];
    return $w*$this->FontSize/1000;
}

function SetLineWidth($width) {
    $this->LineWidth = $width;
    if($this->page>0) $this->_out(sprintf('%.2F w',$width*$this->k));
}

function Line($x1, $y1, $x2, $y2) {
    $this->_out(sprintf('%.2F %.2F m %.2F %.2F l S',$x1*$this->k,($this->h-$y1)*$this->k,$x2*$this->k,($this->h-$y2)*$this->k));
}

function Rect($x, $y, $w, $h, $style='') {
    if($style=='F') $op = 'f';
    elseif($style=='FD' || $style=='DF') $op = 'B';
    else $op = 'S';
    $this->_out(sprintf('%.2F %.2F %.2F %.2F re %s',$x*$this->k,($this->h-$y)*$this->k,$w*$this->k,-$h*$this->k,$op));
}

function SetFont($family, $style='', $size=0) {
    if($family=='') $family = $this->FontFamily;
    else $family = strtolower($family);
    $style = strtoupper($style);
    if(strpos($style,'U')!==false) {
        $this->underline = true;
        $style = str_replace('U','',$style);
    } else $this->underline = false;
    if($style=='IB') $style = 'BI';
    if($size==0) $size = $this->FontSizePt;
    if($this->FontFamily==$family && $this->FontStyle==$style && $this->FontSizePt==$size) return;
    $fontkey = $family.$style;
    if(!isset($this->fonts[$fontkey])) {
        if($family=='arial') $family = 'helvetica';
        if(in_array($family,$this->CoreFonts)) {
            if($family=='symbol' || $family=='zapfdingbats') $style = '';
            $fontkey = $family.$style;
            if(!isset($this->fonts[$fontkey])) $this->AddFont($family,$style);
        } else $this->Error('Undefined font: '.$family.' '.$style);
    }
    $this->FontFamily = $family;
    $this->FontStyle = $style;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    $this->CurrentFont = $this->fonts[$fontkey];
    if($this->page>0) $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function AddFont($family, $style='', $file='') {
    $family = strtolower($family);
    if($file=='') $file = str_replace(' ','',$family).strtolower($style).'.php';
    $style = strtoupper($style);
    if($style=='IB') $style = 'BI';
    $fontkey = $family.$style;
    if(isset($this->fonts[$fontkey])) return;
    $info = $this->_loadfont($file);
    $info['i'] = count($this->fonts)+1;
    $this->fonts[$fontkey] = $info;
}

function SetFontSize($size) {
    if($this->FontSizePt==$size) return;
    $this->FontSizePt = $size;
    $this->FontSize = $size/$this->k;
    if($this->page>0 && $this->FontFamily) $this->_out(sprintf('BT /F%d %.2F Tf ET',$this->CurrentFont['i'],$this->FontSizePt));
}

function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
    $k = $this->k;
    if($this->y+$h>$this->PageBreakTrigger && !$this->InHeader && !$this->InFooter && $this->AcceptPageBreak()) {
        $x = $this->x;
        $ws = $this->ws;
        if($ws>0) { $this->ws = 0; $this->_out('0 Tw'); }
        $this->AddPage($this->CurOrientation,$this->CurPageSize,$this->CurRotation);
        $this->x = $x;
        if($ws>0) { $this->ws = $ws; $this->_out(sprintf('%.3F Tw',$ws*$k)); }
    }
    if($w==0) $w = $this->w-$this->rMargin-$this->x;
    $s = '';
    if($fill || $border==1) {
        if($fill) $op = ($border==1) ? 'B' : 'f';
        else $op = 'S';
        $s = sprintf('%.2F %.2F %.2F %.2F re %s ',$this->x*$k,($this->h-$this->y)*$k,$w*$k,-$h*$k,$op);
    }
    if(is_string($border)) {
        $x = $this->x; $y = $this->y;
        if(strpos($border,'L')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,$x*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'T')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-$y)*$k);
        if(strpos($border,'R')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',($x+$w)*$k,($this->h-$y)*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
        if(strpos($border,'B')!==false)
            $s .= sprintf('%.2F %.2F m %.2F %.2F l S ',$x*$k,($this->h-($y+$h))*$k,($x+$w)*$k,($this->h-($y+$h))*$k);
    }
    if($txt!=='') {
        if(!isset($this->CurrentFont)) $this->Error('No font has been set');
        if($align=='R') $dx = $w-$this->cMargin-$this->GetStringWidth($txt);
        elseif($align=='C') $dx = ($w-$this->GetStringWidth($txt))/2;
        else $dx = $this->cMargin;
        if($this->ColorFlag) $s .= 'q '.$this->TextColor.' ';
        $s .= sprintf('BT %.2F %.2F Td (%s) Tj ET',($this->x+$dx)*$k,($this->h-($this->y+.5*$h+.3*$this->FontSize))*$k,$this->_escape($txt));
        if($this->underline) $s .= ' '.$this->_dounderline($this->x+$dx,$this->y+.5*$h+.3*$this->FontSize,$txt);
        if($this->ColorFlag) $s .= ' Q';
        if($link) $this->Link($this->x+$dx,$this->y+.5*$h-.5*$this->FontSize,$this->GetStringWidth($txt),$this->FontSize,$link);
    }
    if($s) $this->_out($s);
    $this->lasth = $h;
    if($ln>0) { $this->y += $h; if($ln==1) $this->x = $this->lMargin; }
    else $this->x += $w;
}

function AcceptPageBreak() {
    return $this->AutoPageBreak;
}

function Ln($h=null) {
    $this->x = $this->lMargin;
    if($h===null) $this->y += $this->lasth;
    else $this->y += $h;
}

function Output($dest='', $name='', $isUTF8=false) {
    $this->Close();
    if(strlen($name)==1 && strlen($dest)!=1) { $tmp = $dest; $dest = $name; $name = $tmp; }
    if($dest=='') $dest = 'I';
    if($name=='') $name = 'doc.pdf';
    switch(strtoupper($dest)) {
        case 'I':
            if(PHP_SAPI!='cli') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="'.$name.'"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
            }
            echo $this->buffer;
            break;
        case 'D':
            header('Content-Type: application/x-download');
            header('Content-Disposition: attachment; filename="'.$name.'"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            echo $this->buffer;
            break;
        case 'F':
            if(!file_put_contents($name,$this->buffer))
                $this->Error('Unable to create output file: '.$name);
            break;
        case 'S':
            return $this->buffer;
        default:
            $this->Error('Incorrect output destination: '.$dest);
    }
    return '';
}

function _getpagesize($size) {
    if(is_string($size)) {
        $size = strtolower($size);
        $a = $this->StdPageSizes[$size];
        return array($a[0]/$this->k, $a[1]/$this->k);
    } else {
        if($size[0]>$size[1]) return array($size[1], $size[0]);
        else return $size;
    }
}

function _beginpage($orientation, $size, $rotation) {
    $this->page++;
    $this->pages[$this->page] = '';
    $this->state = 2;
    $this->x = $this->lMargin;
    $this->y = $this->tMargin;
    $this->FontFamily = '';
    if($orientation=='') $orientation = $this->DefOrientation;
    else $orientation = strtoupper($orientation[0]);
    if($size=='') $size = $this->DefPageSize;
    else $size = $this->_getpagesize($size);
    if($orientation!=$this->CurOrientation || $size[0]!=$this->CurPageSize[0] || $size[1]!=$this->CurPageSize[1]) {
        if($orientation=='P') { $this->w = $size[0]; $this->h = $size[1]; }
        else { $this->w = $size[1]; $this->h = $size[0]; }
        $this->wPt = $this->w*$this->k;
        $this->hPt = $this->h*$this->k;
        $this->PageBreakTrigger = $this->h-$this->bMargin;
        $this->CurOrientation = $orientation;
        $this->CurPageSize = $size;
    }
}

function _endpage() {
    $this->state = 1;
}

function _loadfont($file) {
    if(file_exists($file)) include($file);
    else $this->Error('Could not include font definition file: '.$file);
    if(!isset($name)) $this->Error('Could not include font definition file: '.$file);
    if(!isset($type)) $type = 'core';
    if(!isset($up)) $up = -100;
    if(!isset($ut)) $ut = 50;
    if(!isset($cw)) $cw = array();
    return get_defined_vars();
}

function _escape($s) {
    return str_replace(')','\\)',str_replace('(','\\(',str_replace('\\','\\\\',$s)));
}

function _dounderline($x, $y, $txt) {
    $up = $this->CurrentFont['up'];
    $ut = $this->CurrentFont['ut'];
    $w = $this->GetStringWidth($txt)+$this->ws*substr_count($txt,' ');
    return sprintf('%.2F %.2F %.2F %.2F re f',$x*$this->k,($this->h-($y-$up/1000*$this->FontSize))*$this->k,$w*$this->k,-$ut/1000*$this->FontSizePt);
}

function _out($s) {
    if($this->state==2) $this->pages[$this->page] .= $s."\n";
    else $this->buffer .= $s."\n";
}

function _put($s) {
    $this->buffer .= $s;
}

function _putstream($s) {
    if($this->compress) {
        $op = '/Filter /FlateDecode ';
        $s = gzcompress($s);
    } else $op = '';
    $this->_put($op.'/Length '.strlen($s).'>>');
    $this->_put('stream');
    $this->_put($s);
    $this->_put('endstream');
}

function _enddoc() {
    $this->_putheader();
    $this->_putpages();
    $this->_putresources();
    $this->_putinfo();
    $this->_putcatalog();
    $o = strlen($this->buffer);
    $this->_put('xref');
    $this->_put('0 '.($this->n+1));
    $this->_put('0000000000 65535 f ');
    for($i=1;$i<=$this->n;$i++)
        $this->_put(sprintf('%010d 00000 n ',$this->offsets[$i]));
    $this->_put('trailer');
    $this->_put('<</Size '.($this->n+1));
    $this->_put('/Root '.$this->n.' 0 R');
    $this->_put('/Info '.($this->n-1).' 0 R>>');
    $this->_put('startxref');
    $this->_put($o);
    $this->_put('%%EOF');
    $this->state = 3;
}

function _putheader() {
    $this->_put('%PDF-'.$this->PDFVersion);
}

function _putpages() {
    $nb = $this->page;
    for($n=1;$n<=$nb;$n++) {
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');
        $this->_put('/Resources 2 0 R');
        $this->_put('/Contents '.($this->n+1).' 0 R>>');
        $this->_put('endobj');
        $p = ($this->compress) ? gzcompress($this->pages[$n]) : $this->pages[$n];
        $this->_putstreamobject($p);
    }
    $this->_newobj();
    $this->_put('<</Type /Pages');
    $kids = '/Kids [';
    for($n=1;$n<=$nb;$n++) $kids .= (1+2*($n-1)).' 0 R ';
    $kids .= ']';
    $this->_put($kids);
    $this->_put('/Count '.$nb.'>>');
    $this->_put('endobj');
}

function _putstreamobject($s) {
    $this->_newobj();
    $this->_putstream($s);
    $this->_put('endobj');
}

function _putresources() {
    $this->_putfonts();
    $this->_putimages();
    $this->_newobj();
    $this->_put('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
    $this->_put('/Font <<');
    foreach($this->fonts as $font) $this->_put('/F'.$font['i'].' '.$font['n'].' 0 R');
    $this->_put('>>');
    if(count($this->images)) {
        $this->_put('/XObject <<');
        foreach($this->images as $image) $this->_put('/I'.$image['i'].' '.$image['n'].' 0 R');
        $this->_put('>>');
    }
    $this->_put('>>');
    $this->_put('endobj');
}

function _putfonts() {
    foreach($this->fonts as $k=>$font) {
        $this->fonts[$k]['n'] = $this->n+1;
        $this->_newobj();
        $this->_put('<</Type /Font');
        $this->_put('/BaseFont /'.$font['name']);
        $this->_put('/Subtype /Type1');
        if($font['name']!='Symbol' && $font['name']!='ZapfDingbats')
            $this->_put('/Encoding /WinAnsiEncoding');
        $this->_put('>>');
        $this->_put('endobj');
    }
}

function _putimages() {
    foreach(array_keys($this->images) as $file) {
        // Placeholder for image support
    }
}

function _putinfo() {
    $this->_newobj();
    $this->_put('<</Producer (FPDF '.FPDF_VERSION.')');
    if(isset($this->metadata['Title'])) $this->_put('/Title '.$this->_textstring($this->metadata['Title']));
    if(isset($this->metadata['Author'])) $this->_put('/Author '.$this->_textstring($this->metadata['Author']));
    $this->_put('>>');
    $this->_put('endobj');
}

function _putcatalog() {
    $this->_newobj();
    $this->_put('<</Type /Catalog');
    $this->_put('/Pages 1 0 R');
    if($this->ZoomMode=='fullpage') $this->_put('/OpenAction [3 0 R /Fit]');
    elseif($this->ZoomMode=='fullwidth') $this->_put('/OpenAction [3 0 R /FitH null]');
    elseif($this->ZoomMode=='real') $this->_put('/OpenAction [3 0 R /XYZ null null 1]');
    elseif(!is_string($this->ZoomMode)) $this->_put('/OpenAction [3 0 R /XYZ null null '.sprintf('%.2F',$this->ZoomMode/100).']');
    if($this->LayoutMode=='single') $this->_put('/PageLayout /SinglePage');
    elseif($this->LayoutMode=='continuous') $this->_put('/PageLayout /OneColumn');
    elseif($this->LayoutMode=='two') $this->_put('/PageLayout /TwoColumnLeft');
    $this->_put('>>');
    $this->_put('endobj');
}

function _newobj() {
    $this->n++;
    $this->offsets[$this->n] = strlen($this->buffer);
    $this->_put($this->n.' 0 obj');
}

function _textstring($s) {
    if(!$this->_isascii($s)) $s = $this->_UTF8toUTF16($s);
    return '('.$this->_escape($s).')';
}

function _isascii($s) {
    $nb = strlen($s);
    for($i=0;$i<$nb;$i++) if(ord($s[$i])>127) return false;
    return true;
}

function _UTF8toUTF16($s) {
    $res = "\xFE\xFF";
    $nb = strlen($s);
    $i = 0;
    while($i<$nb) {
        $c1 = ord($s[$i++]);
        if($c1>=224) {
            $c2 = ord($s[$i++]);
            $c3 = ord($s[$i++]);
            $res .= chr((($c1 & 0x0F)<<4) + (($c2 & 0x3C)>>2));
            $res .= chr((($c2 & 0x03)<<6) + ($c3 & 0x3F));
        } elseif($c1>=192) {
            $c2 = ord($s[$i++]);
            $res .= chr(($c1 & 0x1C)>>2);
            $res .= chr((($c1 & 0x03)<<6) + ($c2 & 0x3F));
        } else {
            $res .= "\x00".chr($c1);
        }
    }
    return $res;
}
}
?>