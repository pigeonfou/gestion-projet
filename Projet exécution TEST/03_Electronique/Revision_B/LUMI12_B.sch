<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE eagle SYSTEM "eagle.dtd">
<eagle version="9.6.2">
  <drawing>
    <settings>
      <setting alwaysvectorfont="yes" />
      <setting verticaltext="up" />
    </settings>
    <grid distance="1.27" unitdist="mm" unit="mm" style="lines" multiple="1" display="no" altdistance=".127" altunitdist="mm" altunit="mm" />
    <layers>
      <layer number="1" name="Top" color="4" fill="1" visible="yes" active="yes" />
      <layer number="16" name="Bottom" color="1" fill="1" visible="yes" active="yes" />
      <layer number="17" name="Pads" color="2" fill="1" visible="yes" active="yes" />
      <layer number="18" name="Vias" color="2" fill="1" visible="yes" active="yes" />
      <layer number="19" name="Unrouted" color="6" fill="1" visible="yes" active="yes" />
      <layer number="20" name="Dimension" color="15" fill="1" visible="yes" active="yes" />
      <layer number="21" name="tPlace" color="7" fill="1" visible="yes" active="yes" />
      <layer number="22" name="bPlace" color="7" fill="1" visible="yes" active="yes" />
      <layer number="25" name="tNames" color="7" fill="1" visible="yes" active="yes" />
      <layer number="26" name="bNames" color="7" fill="1" visible="yes" active="yes" />
      <layer number="27" name="tValues" color="7" fill="1" visible="yes" active="yes" />
      <layer number="28" name="bValues" color="7" fill="1" visible="yes" active="yes" />
      <layer number="29" name="tStop" color="7" fill="1" visible="yes" active="yes" />
      <layer number="30" name="bStop" color="7" fill="1" visible="yes" active="yes" />
      <layer number="44" name="Drills" color="7" fill="1" visible="yes" active="yes" />
      <layer number="45" name="Holes" color="7" fill="1" visible="yes" active="yes" />
      <layer number="46" name="Milling" color="3" fill="1" visible="yes" active="yes" />
      <layer number="51" name="tDocu" color="7" fill="1" visible="yes" active="yes" />
      <layer number="52" name="bDocu" color="7" fill="1" visible="yes" active="yes" />
      <layer number="91" name="Nets" color="2" fill="1" visible="yes" active="yes" />
      <layer number="92" name="Busses" color="1" fill="1" visible="yes" active="yes" />
      <layer number="93" name="Pins" color="2" fill="1" visible="yes" active="yes" />
      <layer number="94" name="Symbols" color="4" fill="1" visible="yes" active="yes" />
      <layer number="95" name="Names" color="7" fill="1" visible="yes" active="yes" />
      <layer number="96" name="Values" color="7" fill="1" visible="yes" active="yes" />
      <layer number="97" name="Info" color="7" fill="1" visible="yes" active="yes" />
      <layer number="98" name="Guide" color="6" fill="1" visible="yes" active="yes" />
    </layers>
    <schematic xreflabel="%F%N/%S.%C%R" xrefpart="/%S.%C%R">
      <libraries>
        <library name="LUMI12_FICTIF">
          <packages>
            <package name="AXIAL">
              <description>Empreinte synthetique de recette - FICTIF - non qualifiee pour fabrication.</description>
              <pad name="A" x="-3.81" y="0" drill="1" diameter="1.8" shape="octagon" />
              <pad name="K" x="3.81" y="0" drill="1" diameter="1.8" shape="octagon" />
              <wire x1="-2" y1="-1" x2="2" y2="-1" width="0.15" layer="21" />
              <wire x1="2" y1="-1" x2="2" y2="1" width="0.15" layer="21" />
              <wire x1="2" y1="1" x2="-2" y2="1" width="0.15" layer="21" />
              <wire x1="-2" y1="1" x2="-2" y2="-1" width="0.15" layer="21" />
              <text x="-2" y="2.3" size="0.8" layer="25">&gt;NAME</text>
              <text x="-2" y="-2.8" size="0.65" layer="27">&gt;VALUE</text>
            </package>
            <package name="LED3">
              <description>Empreinte synthetique de recette - FICTIF - non qualifiee pour fabrication.</description>
              <pad name="A" x="-1.27" y="0" drill="1" diameter="1.8" shape="octagon" />
              <pad name="K" x="1.27" y="0" drill="1" diameter="1.8" shape="octagon" />
              <circle x="0" y="0" radius="1.5" width="0.15" layer="21" />
              <text x="-2" y="2.3" size="0.8" layer="25">&gt;NAME</text>
              <text x="-2" y="-2.8" size="0.65" layer="27">&gt;VALUE</text>
            </package>
            <package name="BORNIER5">
              <description>Empreinte synthetique de recette - FICTIF - non qualifiee pour fabrication.</description>
              <pad name="1" x="0" y="0" drill="1" diameter="2.5" shape="octagon" />
              <pad name="2" x="0" y="5.08" drill="1" diameter="2.5" shape="octagon" />
              <wire x1="-2" y1="-2" x2="3" y2="-2" width="0.15" layer="21" />
              <wire x1="3" y1="-2" x2="3" y2="7.08" width="0.15" layer="21" />
              <wire x1="3" y1="7.08" x2="-2" y2="7.08" width="0.15" layer="21" />
              <wire x1="-2" y1="7.08" x2="-2" y2="-2" width="0.15" layer="21" />
              <text x="-2" y="2.3" size="0.8" layer="25">&gt;NAME</text>
              <text x="-2" y="-2.8" size="0.65" layer="27">&gt;VALUE</text>
            </package>
          </packages>
          <symbols>
            <symbol name="CONN2">
              <wire x1="0" y1="-7.62" x2="5.08" y2="-7.62" width="0.15" layer="94" />
              <wire x1="5.08" y1="-7.62" x2="5.08" y2="2.54" width="0.15" layer="94" />
              <wire x1="5.08" y1="2.54" x2="0" y2="2.54" width="0.15" layer="94" />
              <wire x1="0" y1="2.54" x2="0" y2="-7.62" width="0.15" layer="94" />
              <pin name="1" x="-5.08" y="0" visible="pad" length="short" direction="pas" />
              <pin name="2" x="-5.08" y="-5.08" visible="pad" length="short" direction="pas" />
              <text x="-3" y="5" size="1.27" layer="95">&gt;NAME</text>
              <text x="-3" y="-4" size="1.0" layer="96">&gt;VALUE</text>
            </symbol>
            <symbol name="DIODE">
              <pin name="A" x="-5.08" y="0" visible="pad" length="short" direction="pas" />
              <pin name="K" x="5.08" y="0" visible="pad" length="short" direction="pas" rot="R180" />
              <wire x1="-2.54" y1="0" x2="-1.5" y2="0" width="0.254" layer="94" />
              <wire x1="1.5" y1="0" x2="2.54" y2="0" width="0.254" layer="94" />
              <polygon width="0.15" layer="94">
                <vertex x="-1.5" y="-1.5" />
                <vertex x="1.5" y="0" />
                <vertex x="-1.5" y="1.5" />
              </polygon>
              <wire x1="1.5" y1="-1.6" x2="1.5" y2="1.6" width="0.254" layer="94" />
              <text x="-3" y="5" size="1.27" layer="95">&gt;NAME</text>
              <text x="-3" y="-4" size="1.0" layer="96">&gt;VALUE</text>
            </symbol>
            <symbol name="RES">
              <pin name="1" x="-5.08" y="0" visible="pad" length="short" direction="pas" />
              <pin name="2" x="5.08" y="0" visible="pad" length="short" direction="pas" rot="R180" />
              <wire x1="-2.54" y1="-1" x2="2.54" y2="-1" width="0.15" layer="94" />
              <wire x1="2.54" y1="-1" x2="2.54" y2="1" width="0.15" layer="94" />
              <wire x1="2.54" y1="1" x2="-2.54" y2="1" width="0.15" layer="94" />
              <wire x1="-2.54" y1="1" x2="-2.54" y2="-1" width="0.15" layer="94" />
              <text x="-3" y="5" size="1.27" layer="95">&gt;NAME</text>
              <text x="-3" y="-4" size="1.0" layer="96">&gt;VALUE</text>
            </symbol>
            <symbol name="LED">
              <pin name="A" x="-5.08" y="0" visible="pad" length="short" direction="pas" />
              <pin name="K" x="5.08" y="0" visible="pad" length="short" direction="pas" rot="R180" />
              <wire x1="-2.54" y1="0" x2="-1.5" y2="0" width="0.254" layer="94" />
              <wire x1="1.5" y1="0" x2="2.54" y2="0" width="0.254" layer="94" />
              <polygon width="0.15" layer="94">
                <vertex x="-1.5" y="-1.5" />
                <vertex x="1.5" y="0" />
                <vertex x="-1.5" y="1.5" />
              </polygon>
              <wire x1="1.5" y1="-1.6" x2="1.5" y2="1.6" width="0.254" layer="94" />
              <wire x1="-0.5" y1="2" x2="1.2" y2="3.7" width="0.15" layer="94" />
              <wire x1="1.5" y1="2" x2="3.2" y2="3.7" width="0.15" layer="94" />
              <text x="-3" y="5" size="1.27" layer="95">&gt;NAME</text>
              <text x="-3" y="-4" size="1.0" layer="96">&gt;VALUE</text>
            </symbol>
          </symbols>
          <devicesets>
            <deviceset name="CONN2" prefix="C" uservalue="yes">
              <gates>
                <gate name="G$1" symbol="CONN2" x="0" y="0" />
              </gates>
              <devices>
                <device name="" package="BORNIER5">
                  <connects>
                    <connect gate="G$1" pin="1" pad="1" />
                    <connect gate="G$1" pin="2" pad="2" />
                  </connects>
                  <technologies>
                    <technology name="" />
                  </technologies>
                </device>
              </devices>
            </deviceset>
            <deviceset name="DIODE" prefix="D" uservalue="yes">
              <gates>
                <gate name="G$1" symbol="DIODE" x="0" y="0" />
              </gates>
              <devices>
                <device name="" package="AXIAL">
                  <connects>
                    <connect gate="G$1" pin="A" pad="A" />
                    <connect gate="G$1" pin="K" pad="K" />
                  </connects>
                  <technologies>
                    <technology name="" />
                  </technologies>
                </device>
              </devices>
            </deviceset>
            <deviceset name="RES" prefix="R" uservalue="yes">
              <gates>
                <gate name="G$1" symbol="RES" x="0" y="0" />
              </gates>
              <devices>
                <device name="" package="AXIAL">
                  <connects>
                    <connect gate="G$1" pin="1" pad="A" />
                    <connect gate="G$1" pin="2" pad="K" />
                  </connects>
                  <technologies>
                    <technology name="" />
                  </technologies>
                </device>
              </devices>
            </deviceset>
            <deviceset name="LED" prefix="L" uservalue="yes">
              <gates>
                <gate name="G$1" symbol="LED" x="0" y="0" />
              </gates>
              <devices>
                <device name="" package="LED3">
                  <connects>
                    <connect gate="G$1" pin="A" pad="A" />
                    <connect gate="G$1" pin="K" pad="K" />
                  </connects>
                  <technologies>
                    <technology name="" />
                  </technologies>
                </device>
              </devices>
            </deviceset>
          </devicesets>
        </library>
      </libraries>
      <attributes />
      <variantdefs />
      <classes>
        <class number="0" name="default" width="0.254" drill="0.8" />
      </classes>
      <parts>
        <part name="J1" library="LUMI12_FICTIF" deviceset="CONN2" device="" value="12V" />
        <part name="D1" library="LUMI12_FICTIF" deviceset="DIODE" device="" value="DIO-S-F" />
        <part name="R1" library="LUMI12_FICTIF" deviceset="RES" device="" value="1000" />
        <part name="R2" library="LUMI12_FICTIF" deviceset="RES" device="" value="1000" />
        <part name="LED1" library="LUMI12_FICTIF" deviceset="LED" device="" value="LED3-G-F2" />
        <part name="LED2" library="LUMI12_FICTIF" deviceset="LED" device="" value="LED3-G-F2" />
      </parts>
      <sheets>
        <sheet>
          <plain>
            <text x="0" y="65" size="2" layer="97">LUMI-12 / REV B / FICTIF - RECETTE / NE PAS FABRIQUER</text>
            <text x="0" y="59" size="1.3" layer="97">R1=R2=1000 ohms. Revision A volontairement non conforme ; B apres DM01.</text>
          </plain>
          <instances>
            <instance part="J1" gate="G$1" x="10" y="30" smashed="no" />
            <instance part="D1" gate="G$1" x="35" y="30" smashed="no" />
            <instance part="R1" gate="G$1" x="60" y="40" smashed="no" />
            <instance part="R2" gate="G$1" x="60" y="20" smashed="no" />
            <instance part="LED1" gate="G$1" x="85" y="40" smashed="no" />
            <instance part="LED2" gate="G$1" x="85" y="20" smashed="no" />
          </instances>
          <busses />
          <nets>
            <net name="+12_RAW" class="0">
              <segment>
                <pinref part="J1" gate="G$1" pin="1" />
                <pinref part="D1" gate="G$1" pin="A" />
                <wire x1="4.92" y1="30" x2="29.92" y2="30" width="0.254" layer="91" />
                <label x="4.92" y="30" size="1.0" layer="95" />
              </segment>
            </net>
            <net name="+12_PROT" class="0">
              <segment>
                <pinref part="D1" gate="G$1" pin="K" />
                <pinref part="R1" gate="G$1" pin="1" />
                <pinref part="R2" gate="G$1" pin="1" />
                <wire x1="40.08" y1="30" x2="45" y2="30" width="0.254" layer="91" />
                <wire x1="45" y1="30" x2="45" y2="40" width="0.254" layer="91" />
                <wire x1="45" y1="40" x2="54.92" y2="40" width="0.254" layer="91" />
                <wire x1="45" y1="30" x2="45" y2="20" width="0.254" layer="91" />
                <wire x1="45" y1="20" x2="54.92" y2="20" width="0.254" layer="91" />
                <junction x="45" y="30" />
                <label x="40.08" y="30" size="1.0" layer="95" />
              </segment>
            </net>
            <net name="LED1_A" class="0">
              <segment>
                <pinref part="R1" gate="G$1" pin="2" />
                <pinref part="LED1" gate="G$1" pin="A" />
                <wire x1="65.08" y1="40" x2="79.92" y2="40" width="0.254" layer="91" />
                <label x="65.08" y="40" size="1.0" layer="95" />
              </segment>
            </net>
            <net name="LED2_A" class="0">
              <segment>
                <pinref part="R2" gate="G$1" pin="2" />
                <pinref part="LED2" gate="G$1" pin="A" />
                <wire x1="65.08" y1="20" x2="79.92" y2="20" width="0.254" layer="91" />
                <label x="65.08" y="20" size="1.0" layer="95" />
              </segment>
            </net>
            <net name="GND" class="0">
              <segment>
                <pinref part="J1" gate="G$1" pin="2" />
                <pinref part="LED1" gate="G$1" pin="K" />
                <pinref part="LED2" gate="G$1" pin="K" />
                <wire x1="4.92" y1="24.92" x2="2" y2="24.92" width="0.254" layer="91" />
                <wire x1="2" y1="24.92" x2="2" y2="10" width="0.254" layer="91" />
                <wire x1="2" y1="10" x2="100" y2="10" width="0.254" layer="91" />
                <wire x1="100" y1="10" x2="100" y2="20" width="0.254" layer="91" />
                <wire x1="100" y1="20" x2="100" y2="40" width="0.254" layer="91" />
                <wire x1="90.08" y1="20" x2="100" y2="20" width="0.254" layer="91" />
                <wire x1="90.08" y1="40" x2="100" y2="40" width="0.254" layer="91" />
                <junction x="100" y="20" />
                <label x="4.92" y="24.92" size="1.0" layer="95" />
              </segment>
            </net>
          </nets>
        </sheet>
      </sheets>
      <errors />
    </schematic>
  </drawing>
</eagle>
