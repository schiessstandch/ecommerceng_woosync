-- ===================================================================
-- Copyright (C) 2024 Easya Solutions <support@easya.solutions>
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
--
-- ===================================================================

create table llx_ecommerceng_remote_shipping_zone_methods
(
	rowid							integer			AUTO_INCREMENT PRIMARY KEY,
	site_id							integer			NOT NULL,
    remote_zone_id					integer     	NOT NULL,	-- Remote shipping zone ID
    remote_instance_id				integer     	NOT NULL,	-- Remote shipping zone method instance ID
    remote_title				    varchar(255)	NOT NULL,	-- Remote shipping zone method customer facing title
    remote_order				    integer     	NOT NULL,	-- Remote shipping zone method sort order
    remote_enabled				    integer(1)     	NOT NULL,	-- Remote shipping zone method enabled status
    remote_method_id				varchar(255)	NOT NULL,	-- Remote shipping zone method ID
	remote_method_title				varchar(255)	NOT NULL,	-- Remote shipping zone method title
    remote_method_description		TEXT        	NOT NULL,	-- Remote shipping zone method description
	warehouse_id					integer,					-- Dolibarr warehouse ID
	old_entry						tinyint(1),					-- Flag for set if this shipping zone method has been delete on WooCommerce
	entity							integer			DEFAULT 1
) ENGINE=InnoDB;